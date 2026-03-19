<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\UserBiometricInfo;
use App\Models\Checkinout;
use App\Models\OfficeShift;
use App\Models\UserContact;
use App\Models\UserProfile;
use App\Models\Department;
use App\Models\College;
use Illuminate\Validation\Rule;

class UserController extends Controller
{

    private function isSuperAdmin(Request $request): bool
    {
        return (int) ($request->user()?->role ?? -1) === 1;
    }

    private function forbiddenResponse(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'message' => 'Forbidden',
        ], 403);
    }

    private function canAccessUser(Request $request, int $targetUserId): bool
    {
        if ($this->isSuperAdmin($request)) {
            return true;
        }

        return (int) ($request->user()?->id ?? 0) === (int) $targetUserId;
    }

    private function getPrimaryContactValue($contacts, array $preferredTypes = []): ?string
    {
        foreach ($preferredTypes as $type) {
            $contact = $contacts->first(function ($item) use ($type) {
                return ($item->type ?? null) === $type && !empty($item->value);
            });

            if ($contact) {
                return $contact->value;
            }
        }

        $fallback = $contacts->first(fn ($item) => !empty($item->value));
        return $fallback?->value;
    }

    private function buildDerivedBiometricPayload(User $user): array
    {
        $user->loadMissing(['profile', 'contacts', 'addresses']);

        $profile = $user->profile;
        $primaryAddress = $user->addresses->firstWhere('is_primary', true) ?? $user->addresses->first();
        $contacts = $user->contacts;

        return array_filter([
            'USERID' => $user->id,
            'Badgenumber' => $user->id,
            'Name' => $user->name,
            'Gender' => $profile?->gender,
            'BIRTHDAY' => $profile?->dob,
            'street' => trim(implode(', ', array_filter([
                $primaryAddress?->address1,
                $primaryAddress?->address2,
                $primaryAddress?->barangay,
            ]))),
            'CITY' => $primaryAddress?->municipality,
            'STATE' => $primaryAddress?->province,
            'ZIP' => $primaryAddress?->zipcode,
            'OPHONE' => $this->getPrimaryContactValue($contacts, ['mobile', 'phone']),
            'FPHONE' => $this->getPrimaryContactValue($contacts, ['phone', 'mobile']),
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function syncBiometricInfo(User $user, array $validated, ?int $actorId): void
    {
        $biometricInfo = $validated['biometric_info'] ?? null;
        $fillable = (new UserBiometricInfo())->getFillable();
        $payload = is_array($biometricInfo)
            ? array_intersect_key($biometricInfo, array_flip($fillable))
            : [];

        $payload = array_merge($payload, $this->buildDerivedBiometricPayload($user));
        unset($payload['USERID']);

        if (empty($payload)) {
            return;
        }

        $existing = UserBiometricInfo::withTrashed()->where('USERID', $user->id)->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }
            $existing->update($payload);
            return;
        }

        UserBiometricInfo::create(array_merge($payload, [
            'USERID' => $user->id,
        ]));
    }

    private function buildContactsPayload(array $validated): array
    {
        $contacts = $validated['contacts'] ?? [];

        if (empty($contacts) && (!empty($validated['contact_type']) || !empty($validated['contact_value']))) {
            $contacts = [[
                'type' => $validated['contact_type'] ?? 'mobile',
                'value' => $validated['contact_value'] ?? '',
                'is_primary' => true,
            ]];
        }

        $contacts = array_values(array_filter($contacts, function ($contact) {
            return !empty($contact['type'] ?? null) || !empty($contact['value'] ?? null);
        }));

        if (!empty($contacts)) {
            $hasPrimary = collect($contacts)->contains(fn ($item) => (bool) ($item['is_primary'] ?? false));
            if (!$hasPrimary) {
                $contacts[0]['is_primary'] = true;
            }
        }

        return $contacts;
    }

    private function buildAddressesPayload(array $validated): array
    {
        $addresses = $validated['addresses'] ?? [];

        if (empty($addresses) && !empty($validated['address1'])) {
            $addresses = [[
                'label' => $validated['address_label'] ?? 'home',
                'address1' => $validated['address1'],
                'address2' => $validated['address2'] ?? null,
                'barangay' => $validated['barangay'] ?? null,
                'municipality' => $validated['municipality'] ?? null,
                'province' => $validated['province'] ?? null,
                'zipcode' => $validated['zipcode'] ?? null,
                'is_primary' => true,
            ]];
        }

        $addresses = array_values(array_filter($addresses, function ($address) {
            if (!empty($address['id'] ?? null)) {
                return true;
            }

            foreach (['address1', 'address2', 'barangay', 'municipality', 'province', 'zipcode'] as $field) {
                if (!empty($address[$field] ?? null)) {
                    return true;
                }
            }

            return false;
        }));

        if (!empty($addresses)) {
            $hasPrimary = collect($addresses)->contains(fn ($item) => (bool) ($item['is_primary'] ?? false));
            if (!$hasPrimary) {
                $addresses[0]['is_primary'] = true;
            }
        }

        return $addresses;
    }

    private function syncContacts(User $user, array $contacts, ?int $actorId): void
    {
        if (empty($contacts)) {
            UserContact::where('user_id', $user->id)->delete();
            return;
        }

        $keptIds = [];
        foreach ($contacts as $index => $contact) {
            $payload = [
                'type' => $contact['type'] ?? 'mobile',
                'value' => $contact['value'] ?? '',
                'is_primary' => (bool) ($contact['is_primary'] ?? false),
                'user_last_modify' => $actorId,
            ];

            if (!empty($contact['id'])) {
                $model = UserContact::where('user_id', $user->id)->where('id', $contact['id'])->first();
                if ($model) {
                    $model->update($payload);
                    $keptIds[] = $model->id;
                    continue;
                }
            }

            $created = UserContact::create(array_merge($payload, [
                'user_id' => $user->id,
                'user_add' => $actorId,
            ]));
            $keptIds[] = $created->id;
        }

        UserContact::where('user_id', $user->id)->whereNotIn('id', $keptIds)->delete();

        $primaryId = UserContact::where('user_id', $user->id)
            ->whereIn('id', $keptIds)
            ->where('is_primary', true)
            ->value('id');

        if (!$primaryId) {
            $primaryId = $keptIds[0] ?? null;
        }

        if ($primaryId) {
            UserContact::where('user_id', $user->id)->update(['is_primary' => false]);
            UserContact::where('id', $primaryId)->update(['is_primary' => true]);
        }
    }

    private function syncAddresses(User $user, array $addresses, ?int $actorId): void
    {
        if (empty($addresses)) {
            UserAddress::where('user_id', $user->id)->delete();
            return;
        }

        $keptIds = [];
        foreach ($addresses as $address) {
            $payload = [
                'label' => $address['label'] ?? 'home',
                'address1' => $address['address1'] ?? '',
                'address2' => $address['address2'] ?? null,
                'barangay' => $address['barangay'] ?? null,
                'municipality' => $address['municipality'] ?? null,
                'province' => $address['province'] ?? null,
                'zipcode' => $address['zipcode'] ?? null,
                'is_primary' => (bool) ($address['is_primary'] ?? false),
                'user_last_modify' => $actorId,
            ];

            if (!empty($address['id'])) {
                $model = UserAddress::where('user_id', $user->id)->where('id', $address['id'])->first();
                if ($model) {
                    $model->update($payload);
                    $keptIds[] = $model->id;
                    continue;
                }
            }

            $created = UserAddress::create(array_merge($payload, [
                'user_id' => $user->id,
                'user_add' => $actorId,
            ]));
            $keptIds[] = $created->id;
        }

        UserAddress::where('user_id', $user->id)->whereNotIn('id', $keptIds)->delete();

        $primaryId = UserAddress::where('user_id', $user->id)
            ->whereIn('id', $keptIds)
            ->where('is_primary', true)
            ->value('id');

        if (!$primaryId) {
            $primaryId = $keptIds[0] ?? null;
        }

        if ($primaryId) {
            UserAddress::where('user_id', $user->id)->update(['is_primary' => false]);
            UserAddress::where('id', $primaryId)->update(['is_primary' => true]);
        }
    }

    private function mapUser(User $user): array
    {
        $profile = $user->profile;
        $primaryContact = $user->contacts->firstWhere('is_primary', true) ?? $user->contacts->first();
        $primaryAddress = $user->addresses->firstWhere('is_primary', true) ?? $user->addresses->first();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'thumbnail' => $profile?->thumbnail,
            'role' => $user->role,
            'status' => $user->status,
            'main_account' => $user->main_account,
            'user_add' => $user->user_add,
            'user_add_name' => $user->addedBy?->name,
            'office_shift_id' => $user->office_shift_id,
            'office_shift' => $user->officeShift,
            'department_id' => $user->department_id,
            'department' => $user->departmentRef?->department_name ?? $user->department,
            'department_ref' => $user->departmentRef,
            'college_id' => $user->college_id,
            'college_ref' => $user->collegeRef,
            'last_login' => $user->last_login,
            'profile' => $profile,
            'contacts' => $user->contacts,
            'addresses' => $user->addresses,
            'biometric_info' => $user->biometricInfo,
            'primary_contact' => $primaryContact,
            'primary_address' => $primaryAddress,
        ];
    }

    private function queryUsers()
    {
        return User::with([
            'addedBy:id,name',
            'profile:id,user_id,first_name,middle_name,last_name,name_extension,dob,gender,image,thumbnail',
            'contacts:id,user_id,type,value,is_primary',
            'addresses:id,user_id,label,address1,address2,barangay,municipality,province,zipcode,is_primary',
            'biometricInfo',
            'officeShift:id,name,schedule,is_flexible',
            'officeShift.schedules:id,office_shift_id,sequence,time_in,time_out,is_next_day',
            'departmentRef:id,department_name,dep_short,status',
            'collegeRef:id,company_id,college_short,college_long,college_head,status',
        ]);
    }

    public function index(Request $request)
    {
        if (!$this->isSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $users = $this->queryUsers()
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->mapUser($user))
            ->values();

        $office_shifts = OfficeShift::query()
            ->orderBy('name')
            ->get(['id', 'name', 'schedule', 'is_flexible']);

        $departments = Department::query()
            ->where('status', true)
            ->orderBy('department_name')
            ->get(['id', 'department_name', 'dep_long', 'dep_short', 'status']);

        $colleges = College::query()
            ->where('status', true)
            ->orderBy('college_long')
            ->get(['id', 'company_id', 'college_short', 'college_long', 'college_head', 'status']);

        return response()->json(compact('users', 'office_shifts', 'departments', 'colleges'));
    }

    public function checkinout(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'year' => ['nullable', 'integer', 'min:1970', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        if (!$this->canAccessUser($request, (int) $validated['user_id'])) {
            return $this->forbiddenResponse();
        }

        $year = (int) ($validated['year'] ?? now()->year);
        $month = (int) ($validated['month'] ?? now()->month);

        $checkinouts = Checkinout::query()
            ->where('USERID', $validated['user_id'])
            ->whereYear('CHECKTIME', $year)
            ->whereMonth('CHECKTIME', $month)
            ->orderBy('CHECKTIME', 'desc')
            ->get();

        return response()->json([
            'checkinouts' => $checkinouts,
            'year' => $year,
            'month' => $month,
        ]);
    }

    public function updateOfficeShift(Request $request)
    {
        if (!$this->isSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:users,id'],
            'office_shift_id' => ['nullable', 'integer', 'exists:office_shifts,id'],
        ]);

        $user = User::findOrFail($validated['id']);
        $user->update([
            'office_shift_id' => $validated['office_shift_id'] ?? null,
            'user_last_modify' => $request->user()?->id,
        ]);

        $mappedUser = $this->mapUser($this->queryUsers()->findOrFail($user->id));

        return response()->json([
            'message' => 'Success',
            'user' => $mappedUser,
        ]);
    }

    public function updateAffiliation(Request $request)
    {
        if (!$this->isSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:users,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'college_id' => ['nullable', 'integer', 'exists:colleges,id'],
        ]);

        $user = User::findOrFail($validated['id']);

        $payload = [
            'user_last_modify' => $request->user()?->id,
        ];

        if ($request->has('department_id')) {
            $departmentId = $validated['department_id'] ?? null;
            $departmentName = $departmentId
                ? Department::query()->where('id', $departmentId)->value('department_name')
                : null;

            $payload['department_id'] = $departmentId;
            $payload['department'] = $departmentName;
        }

        if ($request->has('college_id')) {
            $payload['college_id'] = $validated['college_id'] ?? null;
        }

        $user->update($payload);

        $mappedUser = $this->mapUser($this->queryUsers()->findOrFail($user->id));

        return response()->json([
            'message' => 'Success',
            'user' => $mappedUser,
        ]);
    }

    public function store(Request $request)
    {
        if (!$this->isSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'integer', 'min:0', 'max:6'],
            'status' => ['required', 'boolean'],
            'office_shift_id' => ['nullable', 'integer', 'exists:office_shifts,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'college_id' => ['nullable', 'integer', 'exists:colleges,id'],

            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'name_extension' => ['nullable', 'string', 'max:50'],
            'dob' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:30'],
            'image' => ['nullable', 'string', 'max:255'],
            'thumbnail' => ['nullable', 'string', 'max:255'],

            'contact_type' => ['nullable', 'string', 'max:50'],
            'contact_value' => ['nullable', 'string', 'max:255'],
            'contacts' => ['nullable', 'array'],
            'contacts.*.id' => ['nullable', 'integer'],
            'contacts.*.type' => ['nullable', 'string', 'max:50'],
            'contacts.*.value' => ['nullable', 'string', 'max:255'],
            'contacts.*.is_primary' => ['nullable', 'boolean'],

            'address_label' => ['nullable', 'string', 'max:50'],
            'address1' => ['nullable', 'string', 'max:255'],
            'address2' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'municipality' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'zipcode' => ['nullable', 'string', 'max:20'],
            'addresses' => ['nullable', 'array'],
            'addresses.*.id' => ['nullable', 'integer'],
            'addresses.*.label' => ['nullable', 'string', 'max:50'],
            'addresses.*.address1' => ['nullable', 'string', 'max:255'],
            'addresses.*.address2' => ['nullable', 'string', 'max:255'],
            'addresses.*.barangay' => ['nullable', 'string', 'max:255'],
            'addresses.*.municipality' => ['nullable', 'string', 'max:255'],
            'addresses.*.province' => ['nullable', 'string', 'max:255'],
            'addresses.*.zipcode' => ['nullable', 'string', 'max:20'],
            'addresses.*.is_primary' => ['nullable', 'boolean'],
            'biometric_info' => ['nullable', 'array'],
        ]);

        $contacts = $this->buildContactsPayload($validated);
        $addresses = $this->buildAddressesPayload($validated);

        $fullName = trim(implode(', ', array_filter([
            $validated['last_name'] ?? null,
            trim(implode(' ', array_filter([
                $validated['first_name'] ?? null,
                $validated['middle_name'] ?? null,
                $validated['name_extension'] ?? null,
            ]))),
        ])));

        $departmentId = $validated['department_id'] ?? null;
        $departmentName = $departmentId
            ? Department::query()->where('id', $departmentId)->value('department_name')
            : null;

        $createdUser = DB::transaction(function () use ($request, $validated, $fullName, $contacts, $addresses, $departmentId, $departmentName) {
            $user = User::create([
                'name' => $fullName,
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => $validated['role'],
                'status' => $validated['status'],
                'office_shift_id' => $validated['office_shift_id'] ?? null,
                'department_id' => $departmentId,
                'college_id' => $validated['college_id'] ?? null,
                'department' => $departmentName,
                'avatar' => $validated['thumbnail'] ?? null,
                'user_add' => $request->user()?->id,
                'user_last_modify' => $request->user()?->id,
            ]);

            UserProfile::create([
                'user_id' => $user->id,
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'name_extension' => $validated['name_extension'] ?? null,
                'dob' => $validated['dob'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'image' => $validated['image'] ?? null,
                'thumbnail' => $validated['thumbnail'] ?? null,
                'user_add' => $request->user()?->id,
                'user_last_modify' => $request->user()?->id,
            ]);

            $this->syncContacts($user, $contacts, $request->user()?->id);
            $this->syncAddresses($user, $addresses, $request->user()?->id);
            $this->syncBiometricInfo($user, $validated, $request->user()?->id);

            return $user;
        });

        $user = $this->mapUser($this->queryUsers()->findOrFail($createdUser->id));

        return response()->json([
            'message' => 'Success',
            'user' => $user,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:users,id'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($request->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['required', 'integer', 'min:0', 'max:6'],
            'status' => ['required', 'boolean'],
            'office_shift_id' => ['nullable', 'integer', 'exists:office_shifts,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'college_id' => ['nullable', 'integer', 'exists:colleges,id'],

            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'name_extension' => ['nullable', 'string', 'max:50'],
            'dob' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:30'],
            'image' => ['nullable', 'string', 'max:255'],
            'thumbnail' => ['nullable', 'string', 'max:255'],

            'contact_type' => ['nullable', 'string', 'max:50'],
            'contact_value' => ['nullable', 'string', 'max:255'],
            'contacts' => ['nullable', 'array'],
            'contacts.*.id' => ['nullable', 'integer'],
            'contacts.*.type' => ['nullable', 'string', 'max:50'],
            'contacts.*.value' => ['nullable', 'string', 'max:255'],
            'contacts.*.is_primary' => ['nullable', 'boolean'],

            'address_label' => ['nullable', 'string', 'max:50'],
            'address1' => ['nullable', 'string', 'max:255'],
            'address2' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'municipality' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'zipcode' => ['nullable', 'string', 'max:20'],
            'addresses' => ['nullable', 'array'],
            'addresses.*.id' => ['nullable', 'integer'],
            'addresses.*.label' => ['nullable', 'string', 'max:50'],
            'addresses.*.address1' => ['nullable', 'string', 'max:255'],
            'addresses.*.address2' => ['nullable', 'string', 'max:255'],
            'addresses.*.barangay' => ['nullable', 'string', 'max:255'],
            'addresses.*.municipality' => ['nullable', 'string', 'max:255'],
            'addresses.*.province' => ['nullable', 'string', 'max:255'],
            'addresses.*.zipcode' => ['nullable', 'string', 'max:20'],
            'addresses.*.is_primary' => ['nullable', 'boolean'],
            'biometric_info' => ['nullable', 'array'],
        ]);

        if (!$this->canAccessUser($request, (int) $validated['id'])) {
            return $this->forbiddenResponse();
        }

        $contacts = $this->buildContactsPayload($validated);
        $addresses = $this->buildAddressesPayload($validated);

        $fullName = trim(implode(', ', array_filter([
            $validated['last_name'] ?? null,
            trim(implode(' ', array_filter([
                $validated['first_name'] ?? null,
                $validated['middle_name'] ?? null,
                $validated['name_extension'] ?? null,
            ]))),
        ])));

        DB::transaction(function () use ($request, $validated, $fullName, $contacts, $addresses) {
            $user = User::findOrFail($validated['id']);

            $isSuperAdmin = $this->isSuperAdmin($request);

            $userPayload = [
                'name' => $fullName,
                'email' => $validated['email'],
                'role' => $isSuperAdmin ? $validated['role'] : $user->role,
                'status' => $isSuperAdmin ? $validated['status'] : $user->status,
                'office_shift_id' => $isSuperAdmin ? ($validated['office_shift_id'] ?? null) : $user->office_shift_id,
                'avatar' => $validated['thumbnail'] ?? $user->avatar,
                'user_last_modify' => $request->user()?->id,
            ];

            if ($isSuperAdmin && array_key_exists('department_id', $validated)) {
                $departmentId = $validated['department_id'] ?? null;
                $departmentName = $departmentId
                    ? Department::query()->where('id', $departmentId)->value('department_name')
                    : null;

                $userPayload['department_id'] = $departmentId;
                $userPayload['department'] = $departmentName;
            }

            if ($isSuperAdmin && array_key_exists('college_id', $validated)) {
                $userPayload['college_id'] = $validated['college_id'] ?? null;
            }

            if (!empty($validated['password'])) {
                $userPayload['password'] = $validated['password'];
            }

            $user->update($userPayload);

            $profile = UserProfile::where('user_id', $user->id)->first();

            if ($profile) {
                $profile->update([
                    'first_name' => $validated['first_name'],
                    'middle_name' => $validated['middle_name'] ?? null,
                    'last_name' => $validated['last_name'],
                    'name_extension' => $validated['name_extension'] ?? null,
                    'dob' => $validated['dob'] ?? null,
                    'gender' => $validated['gender'] ?? null,
                    'image' => $validated['image'] ?? null,
                    'thumbnail' => $validated['thumbnail'] ?? null,
                    'user_last_modify' => $request->user()?->id,
                ]);
            } else {
                UserProfile::create([
                    'user_id' => $user->id,
                    'first_name' => $validated['first_name'],
                    'middle_name' => $validated['middle_name'] ?? null,
                    'last_name' => $validated['last_name'],
                    'name_extension' => $validated['name_extension'] ?? null,
                    'dob' => $validated['dob'] ?? null,
                    'gender' => $validated['gender'] ?? null,
                    'image' => $validated['image'] ?? null,
                    'thumbnail' => $validated['thumbnail'] ?? null,
                    'user_add' => $request->user()?->id,
                    'user_last_modify' => $request->user()?->id,
                ]);
            }

            $this->syncContacts($user, $contacts, $request->user()?->id);
            $this->syncAddresses($user, $addresses, $request->user()?->id);
            $this->syncBiometricInfo($user, $validated, $request->user()?->id);
        });

        $user = $this->mapUser($this->queryUsers()->findOrFail($validated['id']));

        return response()->json([
            'message' => 'Success',
            'user' => $user,
        ]);
    }

    public function delete(Request $request)
    {
        if (!$this->isSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = User::findOrFail($validated['id']);

        if ((bool) $user->main_account) {
            return response()->json([
                'message' => 'Main account cannot be deleted',
            ], 422);
        }

        if ((int) $request->user()?->id === (int) $user->id) {
            return response()->json([
                'message' => 'You cannot delete your own account',
            ], 422);
        }

        UserBiometricInfo::where('USERID', $user->id)->delete();
        $user->delete();

        return response()->json([
            'message' => 'Success',
        ]);
    }

     public function user(Request $request) {

        User::where('email',$request->user()->email)
            ->update([
                'last_ip' => $request->ip(),
                'last_login' => DB::Raw('NOW()'),
                'user_agent' =>$request->header('User-Agent')

            ]);


        $user = User::where('users.id',$request->user()->id)
            ->with([
                'profile:id,user_id,first_name,middle_name,last_name,name_extension,dob,gender,image,thumbnail',
                'contacts:id,user_id,type,value,is_primary',
                'addresses:id,user_id,label,address1,address2,barangay,municipality,province,zipcode,is_primary',
                'biometricInfo',
                'officeShift:id,name,schedule,is_flexible',
                'officeShift.schedules:id,office_shift_id,sequence,time_in,time_out,is_next_day',
            ])
            ->select('users.*')
            ->first();


        return compact('user');
    }
}
