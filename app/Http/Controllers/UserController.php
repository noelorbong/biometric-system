<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\UserBiometricInfo;
use App\Models\Checkinout;
use App\Models\OfficeShift;
use App\Models\UserContact;
use App\Models\UserProfile;
use App\Models\BiometricLogOverride;
use App\Models\BiometricTemplate;
use App\Models\Department;
use App\Models\College;
use Illuminate\Validation\Rule;

class UserController extends Controller
{

    private function decodeUserDatTextField(string $bytes): string
    {
        return trim(rtrim($bytes, "\x00 \r\n\t"));
    }

    private function parseUserDatRecords(string $raw): array
    {
        $recordSize = 72;

        if ($raw === '') {
            return [];
        }

        if (strlen($raw) % $recordSize !== 0) {
            throw new \RuntimeException('Invalid user.dat structure. Expected 72-byte records.');
        }

        $rows = [];
        $total = strlen($raw);

        for ($offset = 0, $row = 1; $offset + $recordSize <= $total; $offset += $recordSize, $row++) {
            $record = substr($raw, $offset, $recordSize);

            $uid = unpack('v', substr($record, 0, 2))[1] ?? 0;
            $privilege = ord(substr($record, 2, 1) ?: "\x00");
            $password = $this->decodeUserDatTextField(substr($record, 3, 8));
            $name = $this->decodeUserDatTextField(substr($record, 11, 24));
            $card = unpack('V', substr($record, 35, 4))[1] ?? 0;
            $pin = $this->decodeUserDatTextField(substr($record, 48, 24));

            $resolvedUserId = ctype_digit($pin) ? (int) $pin : null;

            $rows[] = [
                'row' => $row,
                'uid' => (int) $uid,
                'pin' => (string) $pin,
                'name' => (string) $name,
                'password' => (string) $password,
                'privilege' => (int) $privilege,
                'card' => (int) $card,
                'resolved_user_id' => $resolvedUserId,
                'valid_for_import' => $resolvedUserId !== null && $resolvedUserId > 0,
            ];
        }

        return $rows;
    }

    private function enrichUserDatRowsWithExistingFlags(array $rows): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (array $row) => (int) ($row['resolved_user_id'] ?? 0),
            $rows
        ), static fn (int $id) => $id > 0)));

        if ($ids === []) {
            return $rows;
        }

        $existingUsers = User::withTrashed()->whereIn('id', $ids)->get()->keyBy('id');
        $existingUserInfos = UserBiometricInfo::withTrashed()->whereIn('USERID', $ids)->get()->keyBy('USERID');
        $existingProfiles = UserProfile::withTrashed()->whereIn('user_id', $ids)->get()->keyBy('user_id');

        return array_map(function (array $row) use ($existingUsers, $existingUserInfos, $existingProfiles): array {
            $resolved = (int) ($row['resolved_user_id'] ?? 0);

            if ($resolved <= 0) {
                $row['has_existing_id'] = false;
                $row['existing'] = [
                    'user' => false,
                    'userinfo' => false,
                    'profile' => false,
                ];
                return $row;
            }

            $hasUser = $existingUsers->has($resolved);
            $hasUserInfo = $existingUserInfos->has($resolved);
            $hasProfile = $existingProfiles->has($resolved);

            $row['has_existing_id'] = $hasUser || $hasUserInfo || $hasProfile;
            $row['existing'] = [
                'user' => $hasUser,
                'userinfo' => $hasUserInfo,
                'profile' => $hasProfile,
            ];

            return $row;
        }, $rows);
    }

    private function parseBiometricTemplateDatRecords(string $raw): array
    {
        $raw = trim($raw);

        if ($raw === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $rows = [];

        foreach ($lines as $index => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $fields = [];
            foreach (explode("\t", $line) as $part) {
                [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
                $key = trim($key);
                if ($key !== '') {
                    $fields[$key] = $value;
                }
            }

            $pin = trim((string) ($fields['Pin'] ?? ''));
            $fingerId = isset($fields['Index']) && is_numeric($fields['Index']) ? (int) $fields['Index'] : null;
            $valid = isset($fields['Valid']) && is_numeric($fields['Valid']) ? (int) $fields['Valid'] : 0;
            $type = isset($fields['Type']) && is_numeric($fields['Type']) ? (int) $fields['Type'] : null;
            $templateBase64 = trim((string) ($fields['Tmp'] ?? ''));
            $templateBytes = '';
            $templateByteLength = 0;

            if ($templateBase64 !== '') {
                $decoded = base64_decode($templateBase64, true);
                if ($decoded !== false) {
                    $templateBytes = $decoded;
                    $templateByteLength = strlen($decoded);
                }
            }

            $resolvedUserId = ctype_digit($pin) ? (int) $pin : null;
            $validForImport = $resolvedUserId !== null
                && $resolvedUserId > 0
                && $fingerId !== null
                && $fingerId >= 0
                && $fingerId <= 9
                && $valid === 1
                && $templateBytes !== '';

            $rows[] = [
                'row' => $index + 1,
                'pin' => $pin,
                'no' => isset($fields['No']) && is_numeric($fields['No']) ? (int) $fields['No'] : 0,
                'finger_id' => $fingerId,
                'valid' => $valid,
                'duress' => isset($fields['Duress']) && is_numeric($fields['Duress']) ? (int) $fields['Duress'] : 0,
                'type' => $type,
                'major_ver' => isset($fields['MajorVer']) && is_numeric($fields['MajorVer']) ? (int) $fields['MajorVer'] : null,
                'minor_ver' => isset($fields['MinorVer']) && is_numeric($fields['MinorVer']) ? (int) $fields['MinorVer'] : null,
                'format' => isset($fields['Format']) && is_numeric($fields['Format']) ? (int) $fields['Format'] : null,
                'template' => $templateBase64,
                'template_bytes' => $templateByteLength,
                'resolved_user_id' => $resolvedUserId,
                'valid_for_import' => $validForImport,
            ];
        }

        return $rows;
    }

    private function enrichBiometricTemplateRowsWithExistingFlags(array $rows, ?string $machineMarker = null): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (array $row) => (int) ($row['resolved_user_id'] ?? 0),
            $rows
        ), static fn (int $id) => $id > 0)));

        $users = $ids === []
            ? collect()
            : User::query()->whereIn('id', $ids)->get(['id', 'name'])->keyBy('id');

        $templates = $ids === []
            ? collect()
            : BiometricTemplate::query()
                ->whereIn('USERID', $ids)
                ->when($machineMarker === null, fn ($query) => $query->whereNull('EMACHINENUM'))
                ->when($machineMarker !== null, fn ($query) => $query->where('EMACHINENUM', $machineMarker))
                ->get(['USERID', 'FINGERID', 'EMACHINENUM'])
                ->groupBy(fn (BiometricTemplate $template) => $template->USERID . ':' . $template->FINGERID);

        return array_map(function (array $row) use ($users, $templates): array {
            $resolved = (int) ($row['resolved_user_id'] ?? 0);
            $fingerId = (int) ($row['finger_id'] ?? -1);
            $user = $users->get($resolved);
            $hasUser = $user !== null;
            $hasTemplate = $templates->has($resolved . ':' . $fingerId);

            $row['user_exists'] = $hasUser;
            $row['user_name'] = $user?->name;
            $row['has_existing_template'] = $hasTemplate;
            $row['valid_for_import'] = (bool) ($row['valid_for_import'] ?? false) && $hasUser;

            return $row;
        }, $rows);
    }

    private function buildImportedUserEmail(int $resolvedUserId, string $pin): string
    {
        $seed = ctype_digit($pin) ? $pin : (string) $resolvedUserId;
        $base = 'import' . $seed;
        $candidate = $base . '@biometric.local';
        $suffix = 1;

        while (User::withTrashed()->where('email', $candidate)->exists()) {
            $candidate = $base . '+' . $suffix . '@biometric.local';
            $suffix++;
        }

        return $candidate;
    }

    private function parseDeviceName(string $name): array
    {
        $name = trim($name);

        if ($name === '') {
            return ['first_name' => '', 'last_name' => '', 'middle_name' => ''];
        }

        $toTitle = static function (string $segment): string {
            return implode(' ', array_map(
                static fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)) . mb_strtolower(mb_substr($word, 1)),
                array_filter(explode(' ', $segment))
            ));
        };

        if (str_contains($name, ',')) {
            [$rawLast, $rawRest] = explode(',', $name, 2);
            $last = $toTitle(trim($rawLast));
            $parts = array_values(array_filter(explode(' ', trim($rawRest))));

            $first = isset($parts[0]) ? $toTitle($parts[0]) : '';
            $middle = '';

            if (count($parts) > 1) {
                $middle = implode(' ', array_map(static function (string $part) use ($toTitle): string {
                    $part = rtrim($part, '.');
                    return $part !== '' ? $toTitle($part) : '';
                }, array_slice($parts, 1)));
                $middle = trim($middle);
            }

            return ['first_name' => $first, 'last_name' => $last, 'middle_name' => $middle];
        }

        $parts = array_values(array_filter(explode(' ', $name)));

        if (count($parts) === 1) {
            return ['first_name' => $toTitle($parts[0]), 'last_name' => '', 'middle_name' => ''];
        }

        $first = $toTitle(array_shift($parts));
        $last = $toTitle(array_pop($parts));
        $middle = implode(' ', array_map($toTitle, $parts));

        return ['first_name' => $first, 'last_name' => $last, 'middle_name' => $middle];
    }

    private function normalizeImportedProfileName(array $nameParts, ?UserProfile $existingProfile = null): array
    {
        $first = trim((string) ($nameParts['first_name'] ?? ''));
        $last = trim((string) ($nameParts['last_name'] ?? ''));
        $middle = trim((string) ($nameParts['middle_name'] ?? ''));

        if ($first === '' && $existingProfile?->first_name) {
            $first = trim((string) $existingProfile->first_name);
        }

        if ($last === '' && $existingProfile?->last_name) {
            $last = trim((string) $existingProfile->last_name);
        }

        if ($first === '' && $last !== '') {
            $first = $last;
        }

        if ($last === '' && $first !== '') {
            $last = $first;
        }

        if ($first === '' && $last === '') {
            $first = 'Imported';
            $last = 'User';
        }

        return [
            'first_name' => $first,
            'last_name' => $last,
            'middle_name' => $middle !== '' ? $middle : null,
        ];
    }

    public function previewUserDatImport(Request $request)
    {
        if (!$this->isSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $uploaded = $validated['file'];

        if (strtolower((string) $uploaded->getClientOriginalExtension()) !== 'dat') {
            return response()->json(['message' => 'Please upload a .dat file.'], 422);
        }

        $raw = file_get_contents($uploaded->getRealPath());

        if ($raw === false) {
            return response()->json(['message' => 'Unable to read uploaded file.'], 422);
        }

        try {
            $rows = $this->parseUserDatRecords($raw);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $rows = $this->enrichUserDatRowsWithExistingFlags($rows);

        $validRows = array_values(array_filter($rows, static fn (array $row) => (bool) ($row['valid_for_import'] ?? false)));
        $existingCount = count(array_filter($validRows, static fn (array $row) => (bool) ($row['has_existing_id'] ?? false)));

        return response()->json([
            'message' => 'user.dat decoded successfully.',
            'rows' => $rows,
            'summary' => [
                'total_rows' => count($rows),
                'valid_rows' => count($validRows),
                'existing_id_rows' => $existingCount,
                'new_rows' => count($validRows) - $existingCount,
            ],
        ]);
    }

    public function importUserDat(Request $request)
    {
        if (!$this->isSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'selected_user_ids' => ['required', 'array', 'min:1'],
            'selected_user_ids.*' => ['integer', 'min:1'],
            'replace_existing' => ['nullable', 'boolean'],
        ]);

        $replaceExisting = (bool) ($validated['replace_existing'] ?? false);
        $selectedLookup = array_flip(array_values(array_unique(array_map('intval', $validated['selected_user_ids'] ?? []))));

        $rows = $this->enrichUserDatRowsWithExistingFlags($validated['rows']);
        $rowsByUserId = [];

        foreach ($rows as $row) {
            $resolvedId = (int) ($row['resolved_user_id'] ?? (ctype_digit((string) ($row['pin'] ?? '')) ? (int) $row['pin'] : 0));

            if ($resolvedId <= 0 || !isset($selectedLookup[$resolvedId])) {
                continue;
            }

            $row['resolved_user_id'] = $resolvedId;
            $rowsByUserId[$resolvedId] = $row;
        }

        if ($rowsByUserId === []) {
            return response()->json(['message' => 'No valid selected users to import.'], 422);
        }

        $createdUsers = 0;
        $updatedUsers = 0;
        $createdUserInfos = 0;
        $updatedUserInfos = 0;
        $createdProfiles = 0;
        $updatedProfiles = 0;
        $skippedExisting = 0;
        $skippedInvalid = 0;
        $processedIds = [];

        DB::transaction(function () use (
            $rowsByUserId,
            $replaceExisting,
            $request,
            &$createdUsers,
            &$updatedUsers,
            &$createdUserInfos,
            &$updatedUserInfos,
            &$createdProfiles,
            &$updatedProfiles,
            &$skippedExisting,
            &$skippedInvalid,
            &$processedIds
        ) {
            foreach ($rowsByUserId as $resolvedUserId => $row) {
                $pin = (string) ($row['pin'] ?? '');
                $name = trim((string) ($row['name'] ?? ''));
                $plainPassword = (string) ($row['password'] ?? '');
                $privilege = (int) ($row['privilege'] ?? 0);
                $card = (int) ($row['card'] ?? 0);

                if (!ctype_digit($pin)) {
                    $skippedInvalid++;
                    continue;
                }

                $user = User::withTrashed()->find($resolvedUserId);
                $userInfo = UserBiometricInfo::withTrashed()->where('USERID', $resolvedUserId)->first();
                $profile = UserProfile::withTrashed()->where('user_id', $resolvedUserId)->first();

                $hasExisting = $user !== null || $userInfo !== null || $profile !== null;

                if ($hasExisting && !$replaceExisting) {
                    $skippedExisting++;
                    continue;
                }

                if ($user) {
                    if ($user->trashed()) {
                        $user->restore();
                    }

                    $userPayload = [
                        'name' => $name !== '' ? $name : $user->name,
                        'user_last_modify' => $request->user()?->id,
                    ];

                    if ($plainPassword !== '') {
                        $userPayload['password'] = $plainPassword;
                    }

                    $user->update($userPayload);
                    $updatedUsers++;
                } else {
                    $newUser = new User([
                        'name' => $name !== '' ? $name : ('Imported User ' . $resolvedUserId),
                        'email' => $this->buildImportedUserEmail($resolvedUserId, $pin),
                        'password' => $plainPassword !== '' ? $plainPassword : ('biometric-' . $pin),
                        'role' => 0,
                        'status' => true,
                        'user_add' => $request->user()?->id,
                        'user_last_modify' => $request->user()?->id,
                    ]);

                    $newUser->id = $resolvedUserId;
                    $newUser->save();
                    $createdUsers++;
                }

                $userInfoPayload = [
                    'USERID' => $resolvedUserId,
                    'Badgenumber' => $pin,
                    'Name' => $name !== '' ? $name : null,
                    'PASSWORD' => $plainPassword,
                    'privilege' => $privilege,
                    'IDCardNo' => $card > 0 ? (string) $card : null,
                    'user_last_modify' => $request->user()?->id,
                ];

                if ($userInfo) {
                    if ($userInfo->trashed()) {
                        $userInfo->restore();
                    }

                    $userInfo->update($userInfoPayload);
                    $updatedUserInfos++;
                } else {
                    $userInfoPayload['user_add'] = $request->user()?->id;
                    UserBiometricInfo::create($userInfoPayload);
                    $createdUserInfos++;
                }

                $nameParts = $this->parseDeviceName($name);
                $normalizedNameParts = $this->normalizeImportedProfileName($nameParts, $profile);
                $importedDisplayName = $name !== '' ? $name : null;
                $existingDisplayName = $profile ? trim((string) ($profile->display_name ?? '')) : '';
                $profilePayload = [
                    'first_name' => $normalizedNameParts['first_name'],
                    'last_name' => $normalizedNameParts['last_name'],
                    'middle_name' => $normalizedNameParts['middle_name'],
                    'user_last_modify' => $request->user()?->id,
                ];

                if ($importedDisplayName !== null && $existingDisplayName === '') {
                    $profilePayload['display_name'] = $importedDisplayName;
                }

                if ($profile) {
                    if ($profile->trashed()) {
                        $profile->restore();
                    }

                    $profile->update($profilePayload);
                    $updatedProfiles++;
                } else {
                    if ($importedDisplayName !== null) {
                        $profilePayload['display_name'] = $importedDisplayName;
                    }

                    UserProfile::create(array_merge($profilePayload, [
                        'user_id' => $resolvedUserId,
                        'user_add' => $request->user()?->id,
                    ]));
                    $createdProfiles++;
                }

                $processedIds[] = $resolvedUserId;
            }
        });

        return response()->json([
            'message' => 'user.dat import completed.',
            'processed_ids' => $processedIds,
            'summary' => [
                'selected' => count($rowsByUserId),
                'processed' => count($processedIds),
                'created_users' => $createdUsers,
                'updated_users' => $updatedUsers,
                'created_userinfo' => $createdUserInfos,
                'updated_userinfo' => $updatedUserInfos,
                'created_profiles' => $createdProfiles,
                'updated_profiles' => $updatedProfiles,
                'skipped_existing' => $skippedExisting,
                'skipped_invalid' => $skippedInvalid,
                'replace_existing' => $replaceExisting,
            ],
        ]);
    }

    public function previewBiometricTemplateDatImport(Request $request)
    {
        if (!$this->isSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'machine_marker' => ['nullable', 'string', 'max:100'],
        ]);

        $uploaded = $validated['file'];

        if (strtolower((string) $uploaded->getClientOriginalExtension()) !== 'dat') {
            return response()->json(['message' => 'Please upload a .dat file.'], 422);
        }

        $raw = file_get_contents($uploaded->getRealPath());

        if ($raw === false) {
            return response()->json(['message' => 'Unable to read uploaded file.'], 422);
        }

        $machineMarker = trim((string) ($validated['machine_marker'] ?? ''));
        $machineMarker = $machineMarker !== '' ? $machineMarker : null;

        try {
            $rows = $this->parseBiometricTemplateDatRecords($raw);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $rows = $this->enrichBiometricTemplateRowsWithExistingFlags($rows, $machineMarker);

        $validRows = array_values(array_filter($rows, static fn (array $row) => (bool) ($row['valid_for_import'] ?? false)));
        $existingCount = count(array_filter($validRows, static fn (array $row) => (bool) ($row['has_existing_template'] ?? false)));
        $missingUsers = count(array_filter($rows, static fn (array $row) => !(bool) ($row['user_exists'] ?? false)));

        return response()->json([
            'message' => 'biotemplate.dat decoded successfully.',
            'rows' => $rows,
            'summary' => [
                'total_rows' => count($rows),
                'valid_rows' => count($validRows),
                'existing_template_rows' => $existingCount,
                'new_template_rows' => count($validRows) - $existingCount,
                'missing_user_rows' => $missingUsers,
            ],
        ]);
    }

    public function importBiometricTemplateDat(Request $request)
    {
        if (!$this->isSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'selected_keys' => ['required', 'array', 'min:1'],
            'selected_keys.*' => ['string'],
            'replace_existing' => ['nullable', 'boolean'],
            'machine_marker' => ['nullable', 'string', 'max:100'],
        ]);

        $replaceExisting = (bool) ($validated['replace_existing'] ?? false);
        $machineMarker = trim((string) ($validated['machine_marker'] ?? ''));
        $machineMarker = $machineMarker !== '' ? $machineMarker : null;
        $selectedLookup = array_flip(array_values(array_unique(array_map('strval', $validated['selected_keys'] ?? []))));
        $rows = $this->enrichBiometricTemplateRowsWithExistingFlags($validated['rows'], $machineMarker);

        $rowsToImport = [];
        foreach ($rows as $row) {
            $key = (int) ($row['resolved_user_id'] ?? 0) . ':' . (int) ($row['finger_id'] ?? -1);

            if (!isset($selectedLookup[$key])) {
                continue;
            }

            $rowsToImport[$key] = $row;
        }

        if ($rowsToImport === []) {
            return response()->json(['message' => 'No valid selected templates to import.'], 422);
        }

        $created = 0;
        $updated = 0;
        $skippedExisting = 0;
        $skippedInvalid = 0;
        $skippedMissingUser = 0;
        $processedKeys = [];

        DB::transaction(function () use (
            $rowsToImport,
            $replaceExisting,
            $machineMarker,
            &$created,
            &$updated,
            &$skippedExisting,
            &$skippedInvalid,
            &$skippedMissingUser,
            &$processedKeys
        ) {
            foreach ($rowsToImport as $key => $row) {
                $userId = (int) ($row['resolved_user_id'] ?? 0);
                $fingerId = (int) ($row['finger_id'] ?? -1);

                if ($userId <= 0 || $fingerId < 0 || $fingerId > 9 || empty($row['template'])) {
                    $skippedInvalid++;
                    continue;
                }

                if (!(bool) ($row['user_exists'] ?? false)) {
                    $skippedMissingUser++;
                    continue;
                }

                $templateRaw = base64_decode((string) $row['template'], true);
                if ($templateRaw === false || $templateRaw === '') {
                    $skippedInvalid++;
                    continue;
                }

                $query = BiometricTemplate::query()
                    ->where('USERID', $userId)
                    ->where('FINGERID', $fingerId);

                if ($machineMarker === null) {
                    $query->whereNull('EMACHINENUM');
                } else {
                    $query->where('EMACHINENUM', $machineMarker);
                }

                $template = $query->first();

                if ($template && !$replaceExisting) {
                    $skippedExisting++;
                    continue;
                }

                if (!$template) {
                    $template = new BiometricTemplate([
                        'USERID' => $userId,
                        'FINGERID' => $fingerId,
                        'EMACHINENUM' => $machineMarker,
                    ]);
                    $created++;
                } else {
                    $updated++;
                }

                $template->TEMPLATE = $templateRaw;
                $template->TEMPLATE4 = $templateRaw;
                $template->USETYPE = 0;
                $template->Flag = (int) ($row['valid'] ?? 1);
                $template->DivisionFP = 10;
                $template->save();

                $processedKeys[] = $key;
            }
        });

        return response()->json([
            'message' => 'biotemplate.dat import completed.',
            'processed_keys' => $processedKeys,
            'summary' => [
                'selected' => count($rowsToImport),
                'processed' => count($processedKeys),
                'created_templates' => $created,
                'updated_templates' => $updated,
                'skipped_existing' => $skippedExisting,
                'skipped_invalid' => $skippedInvalid,
                'skipped_missing_user' => $skippedMissingUser,
                'replace_existing' => $replaceExisting,
                'machine_marker' => $machineMarker,
            ],
        ]);
    }

    private function monthRange(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = (clone $start)->endOfMonth()->endOfDay();

        return [$start, $end];
    }

    private function normalizeCheckType(string $value): string
    {
        return strtoupper(trim($value));
    }

    private function mapOverrideForApi(BiometricLogOverride $override): array
    {
        return [
            'id' => $override->id,
            'user_id' => $override->user_id,
            'checkinout_id' => $override->checkinout_id,
            'action_type' => $override->action_type,
            'old_checktime' => optional($override->old_checktime)->format('Y-m-d H:i:s'),
            'old_checktype' => $override->old_checktype,
            'new_checktime' => optional($override->new_checktime)->format('Y-m-d H:i:s'),
            'new_checktype' => $override->new_checktype,
            'created_by' => $override->created_by,
            'created_by_name' => $override->createdBy?->name,
            'updated_by' => $override->updated_by,
            'updated_by_name' => $override->updatedBy?->name,
            'created_at' => optional($override->created_at)->format('Y-m-d H:i:s'),
            'updated_at' => optional($override->updated_at)->format('Y-m-d H:i:s'),
        ];
    }

    private function buildEffectiveCheckinouts(int $userId, int $year, int $month): array
    {
        [$start, $end] = $this->monthRange($year, $month);

        $baseLogs = Checkinout::query()
            ->where('USERID', $userId)
            ->whereBetween('CHECKTIME', [$start, $end])
            ->orderBy('CHECKTIME', 'desc')
            ->get();

        $overrides = BiometricLogOverride::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->where('user_id', $userId)
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('new_checktime', [$start, $end])
                    ->orWhereBetween('old_checktime', [$start, $end]);
            })
            ->orderByDesc('id')
            ->get();

        $overrideByCheckinout = $overrides
            ->where('action_type', 'override')
            ->whereNotNull('checkinout_id')
            ->groupBy('checkinout_id')
            ->map(fn ($rows) => $rows->sortByDesc('id')->first());

        $effectiveRows = [];

        foreach ($baseLogs as $row) {
            if ($overrideByCheckinout->has($row->id)) {
                continue;
            }

            $effectiveRows[] = [
                'id' => $row->id,
                'USERID' => $row->USERID,
                'CHECKTIME' => optional($row->CHECKTIME)->format('Y-m-d H:i:s'),
                'CHECKTYPE' => $row->CHECKTYPE,
                'VERIFYCODE' => $row->VERIFYCODE,
                'SENSORID' => $row->SENSORID,
                'sn' => $row->sn,
                '_override_id' => null,
                '_override_action' => null,
                '_editable' => false,
            ];
        }

        foreach ($overrides as $override) {
            if (!$override->new_checktime || $override->new_checktime->lt($start) || $override->new_checktime->gt($end)) {
                continue;
            }

            $effectiveRows[] = [
                'id' => $override->checkinout_id ? ('override-' . $override->id) : ('add-' . $override->id),
                'USERID' => $override->user_id,
                'CHECKTIME' => optional($override->new_checktime)->format('Y-m-d H:i:s'),
                'CHECKTYPE' => $override->new_checktype,
                'VERIFYCODE' => null,
                'SENSORID' => null,
                'sn' => null,
                '_override_id' => $override->id,
                '_override_action' => $override->action_type,
                '_editable' => true,
                '_old_checktime' => optional($override->old_checktime)->format('Y-m-d H:i:s'),
                '_old_checktype' => $override->old_checktype,
                '_checkinout_id' => $override->checkinout_id,
            ];
        }

        usort($effectiveRows, function ($a, $b) {
            return strtotime((string) $b['CHECKTIME']) <=> strtotime((string) $a['CHECKTIME']);
        });

        return [
            'checkinouts' => $effectiveRows,
            'overrides' => $overrides->map(fn (BiometricLogOverride $override) => $this->mapOverrideForApi($override))->values(),
        ];
    }

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
            'profile:id,user_id,display_name,first_name,middle_name,last_name,name_extension,dob,gender,image,thumbnail',
            'contacts:id,user_id,type,value,is_primary',
            'addresses:id,user_id,label,address1,address2,barangay,municipality,province,zipcode,is_primary',
            'biometricInfo',
            'officeShift:id,name,schedule,is_flexible,grace_enabled,grace_before_minutes,grace_after_minutes',
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
            ->get(['id', 'name', 'schedule', 'is_flexible', 'grace_enabled', 'grace_before_minutes', 'grace_after_minutes']);

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

        $result = $this->buildEffectiveCheckinouts((int) $validated['user_id'], $year, $month);

        return response()->json([
            'checkinouts' => $result['checkinouts'],
            'overrides' => $result['overrides'],
            'year' => $year,
            'month' => $month,
        ]);
    }

    public function storeCheckinoutOverride(Request $request)
    {
        if (!$this->isSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'action_type' => ['required', Rule::in(['add', 'override'])],
            'checkinout_id' => ['nullable', 'integer', 'exists:checkinout,id'],
            'new_checktime' => ['required', 'date'],
            'new_checktype' => ['required', Rule::in(['I', 'O', 'i', 'o'])],
            'year' => ['nullable', 'integer', 'min:1970', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $actionType = (string) $validated['action_type'];
        $actorId = (int) ($request->user()?->id ?? 0);

        $checkinout = null;
        if ($actionType === 'override') {
            if (empty($validated['checkinout_id'])) {
                return response()->json(['message' => 'checkinout_id is required for override action.'], 422);
            }

            $checkinout = Checkinout::query()->findOrFail((int) $validated['checkinout_id']);
            if ((int) $checkinout->USERID !== (int) $validated['user_id']) {
                return response()->json(['message' => 'Selected checkin log does not belong to user.'], 422);
            }
        }

        $override = BiometricLogOverride::create([
            'user_id' => (int) $validated['user_id'],
            'checkinout_id' => $checkinout?->id,
            'action_type' => $actionType,
            'old_checktime' => $checkinout?->CHECKTIME,
            'old_checktype' => $checkinout?->CHECKTYPE,
            'new_checktime' => Carbon::parse($validated['new_checktime']),
            'new_checktype' => $this->normalizeCheckType((string) $validated['new_checktype']),
            'created_by' => $actorId ?: null,
            'updated_by' => $actorId ?: null,
        ]);

        $year = (int) ($validated['year'] ?? Carbon::parse($validated['new_checktime'])->year);
        $month = (int) ($validated['month'] ?? Carbon::parse($validated['new_checktime'])->month);
        $result = $this->buildEffectiveCheckinouts((int) $validated['user_id'], $year, $month);

        return response()->json([
            'message' => 'Biometric override saved.',
            'override' => $this->mapOverrideForApi($override),
            'checkinouts' => $result['checkinouts'],
            'overrides' => $result['overrides'],
            'year' => $year,
            'month' => $month,
        ]);
    }

    public function updateCheckinoutOverride(Request $request)
    {
        if (!$this->isSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:biometric_log_overrides,id'],
            'new_checktime' => ['required', 'date'],
            'new_checktype' => ['required', Rule::in(['I', 'O', 'i', 'o'])],
            'year' => ['nullable', 'integer', 'min:1970', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $override = BiometricLogOverride::query()->findOrFail((int) $validated['id']);

        $override->update([
            'new_checktime' => Carbon::parse($validated['new_checktime']),
            'new_checktype' => $this->normalizeCheckType((string) $validated['new_checktype']),
            'updated_by' => $request->user()?->id,
        ]);

        $year = (int) ($validated['year'] ?? $override->new_checktime?->year ?? now()->year);
        $month = (int) ($validated['month'] ?? $override->new_checktime?->month ?? now()->month);
        $result = $this->buildEffectiveCheckinouts((int) $override->user_id, $year, $month);

        return response()->json([
            'message' => 'Biometric override updated.',
            'override' => $this->mapOverrideForApi($override->fresh()),
            'checkinouts' => $result['checkinouts'],
            'overrides' => $result['overrides'],
            'year' => $year,
            'month' => $month,
        ]);
    }

    public function deleteCheckinoutOverride(Request $request)
    {
        if (!$this->isSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:biometric_log_overrides,id'],
            'year' => ['nullable', 'integer', 'min:1970', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $override = BiometricLogOverride::query()->findOrFail((int) $validated['id']);
        $userId = (int) $override->user_id;

        $fallbackYear = $override->new_checktime?->year ?? now()->year;
        $fallbackMonth = $override->new_checktime?->month ?? now()->month;

        $override->delete();

        $year = (int) ($validated['year'] ?? $fallbackYear);
        $month = (int) ($validated['month'] ?? $fallbackMonth);
        $result = $this->buildEffectiveCheckinouts($userId, $year, $month);

        return response()->json([
            'message' => 'Biometric override deleted.',
            'checkinouts' => $result['checkinouts'],
            'overrides' => $result['overrides'],
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
            'display_name' => ['nullable', 'string', 'max:255'],
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

        // postpone building contacts/addresses until we know actor permissions
        $contacts = [];
        $addresses = [];

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
                'display_name' => $validated['display_name'] ?? null,
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
            'display_name' => ['nullable', 'string', 'max:255'],
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

        // actor permissions
        $actorRole = (int) ($request->user()?->role ?? -1);
        $isSuperAdmin = $this->isSuperAdmin($request);
        // role 0 users and superadmins (role 1) may edit private profile fields (email, password, image, contacts, addresses)
        $canEditPrivate = $actorRole === 0 || $isSuperAdmin;

        // build contacts/addresses only when actor can edit them
        if ($canEditPrivate) {
            $contacts = $this->buildContactsPayload($validated);
            $addresses = $this->buildAddressesPayload($validated);
        } else {
            // remove private fields from validated to avoid accidental updates
            unset($validated['email'], $validated['password'], $validated['image'], $validated['thumbnail']);
            $validated['contacts'] = [];
            $validated['addresses'] = [];
        }

        DB::transaction(function () use ($request, $validated, $fullName, $contacts, $addresses, $isSuperAdmin, $canEditPrivate) {
            $user = User::findOrFail($validated['id']);

            $userPayload = [
                'name' => $fullName,
                // allow email only when actor may edit private fields
                'email' => $canEditPrivate ? ($validated['email'] ?? $user->email) : $user->email,
                'role' => $isSuperAdmin ? $validated['role'] : $user->role,
                'status' => $isSuperAdmin ? $validated['status'] : $user->status,
                'office_shift_id' => $isSuperAdmin ? ($validated['office_shift_id'] ?? null) : $user->office_shift_id,
                'avatar' => $canEditPrivate ? ($validated['thumbnail'] ?? $user->avatar) : $user->avatar,
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

            if ($canEditPrivate && !empty($validated['password'])) {
                $userPayload['password'] = $validated['password'];
            }

            $user->update($userPayload);

            $profile = UserProfile::where('user_id', $user->id)->first();

            if ($profile) {
                $profile->update([
                    'display_name' => $validated['display_name'] ?? null,
                    'first_name' => $validated['first_name'],
                    'middle_name' => $validated['middle_name'] ?? null,
                    'last_name' => $validated['last_name'],
                    'name_extension' => $validated['name_extension'] ?? null,
                    'dob' => $validated['dob'] ?? null,
                    'gender' => $validated['gender'] ?? null,
                        'image' => $canEditPrivate ? ($validated['image'] ?? null) : $profile->image,
                        'thumbnail' => $canEditPrivate ? ($validated['thumbnail'] ?? null) : $profile->thumbnail,
                    'user_last_modify' => $request->user()?->id,
                ]);
            } else {
                UserProfile::create([
                    'user_id' => $user->id,
                    'display_name' => $validated['display_name'] ?? null,
                    'first_name' => $validated['first_name'],
                    'middle_name' => $validated['middle_name'] ?? null,
                    'last_name' => $validated['last_name'],
                    'name_extension' => $validated['name_extension'] ?? null,
                    'dob' => $validated['dob'] ?? null,
                    'gender' => $validated['gender'] ?? null,
                    'image' => $canEditPrivate ? ($validated['image'] ?? null) : null,
                    'thumbnail' => $canEditPrivate ? ($validated['thumbnail'] ?? null) : null,
                    'user_add' => $request->user()?->id,
                    'user_last_modify' => $request->user()?->id,
                ]);
            }

            if ($canEditPrivate) {
                $this->syncContacts($user, $contacts, $request->user()?->id);
                $this->syncAddresses($user, $addresses, $request->user()?->id);
            }
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
                'last_login' => Carbon::now(),
                'user_agent' =>$request->header('User-Agent')

            ]);


        $user = User::where('users.id',$request->user()->id)
            ->with([
                'profile:id,user_id,display_name,first_name,middle_name,last_name,name_extension,dob,gender,image,thumbnail',
                'contacts:id,user_id,type,value,is_primary',
                'addresses:id,user_id,label,address1,address2,barangay,municipality,province,zipcode,is_primary',
                'biometricInfo',
                'officeShift:id,name,schedule,is_flexible,grace_enabled,grace_before_minutes,grace_after_minutes',
                'officeShift.schedules:id,office_shift_id,sequence,time_in,time_out,is_next_day',
            ])
            ->select('users.*')
            ->first();


        return compact('user');
    }
}
