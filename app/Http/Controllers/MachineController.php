<?php

namespace App\Http\Controllers;

use App\Models\BiometricTemplate;
use App\Models\Checkinout;
use App\Models\Machine;
use App\Models\User;
use App\Models\UserBiometricInfo;
use App\Services\ZKTecoService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class MachineController extends Controller
{
    public function index()
    {
        $machines = Machine::query()
            ->orderBy('MachineAlias')
            ->orderBy('IP')
            ->get();

        return response()->json(compact('machines'));
    }

    public function autoSyncStatus()
    {
        $heartbeat = Cache::get('attendance:auto-sync:daemon:heartbeat');

        $lastHeartbeat = null;
        $sleep = 1;
        $running = false;

        if (is_array($heartbeat)) {
            $lastHeartbeat = $heartbeat['timestamp'] ?? null;
            $sleep = max(1, (int) ($heartbeat['sleep'] ?? 1));

            if ($lastHeartbeat) {
                try {
                    $running = Carbon::parse($lastHeartbeat)->diffInSeconds(now()) <= max(5, $sleep * 3);
                } catch (\Throwable) {
                    $running = false;
                }
            }
        }

        return response()->json([
            'running' => $running,
            'sleep' => $sleep,
            'last_heartbeat' => $lastHeartbeat,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $machine = Machine::create($validated);

        return response()->json([
            'message' => 'Success',
            'machine' => $machine,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate(array_merge([
            'ID' => ['required', 'integer', Rule::exists('machines', 'ID')->whereNull('deleted_at')],
        ], $this->rules((int) $request->input('ID'))));

        $machine = Machine::findOrFail($validated['ID']);
        $machine->update($validated);

        return response()->json([
            'message' => 'Success',
            'machine' => $machine->fresh(),
        ]);
    }

    public function delete(Request $request)
    {
        $validated = $request->validate([
            'ID' => ['required', 'integer', Rule::exists('machines', 'ID')->whereNull('deleted_at')],
        ]);

        $machine = Machine::findOrFail($validated['ID']);
        $machine->delete();

        return response()->json([
            'message' => 'Success',
        ]);
    }

    /**
     * Test connectivity to the biometric device and return its live info.
     */
    public function testConnection(Request $request)
    {
        $validated = $request->validate([
            'ID' => ['required', 'integer', Rule::exists('machines', 'ID')->whereNull('deleted_at')],
        ]);

        $machine = Machine::findOrFail($validated['ID']);

        if (!$machine->IP) {
            return response()->json(['message' => 'Machine has no IP address configured.'], 422);
        }

        $zk = new ZKTecoService(
            ip:       $machine->IP,
            port:     $machine->Port     ?? 4370,
            timeout:  10,
            password: blank($machine->CommPassword) ? '0' : (string) $machine->CommPassword
        );

        try {
            $zk->connect();
            $info = $zk->getDeviceInfo();
            $zk->disconnect();

            $updates = array_filter([
                'sn'              => $info['SerialNumber'] ?? null,
                'FirmwareVersion' => $info['FirmVer']      ?? null,
                'ProductType'     => $info['DeviceName']   ?? null,
                'ProduceKind'     => $info['ProduceKind']  ?? null,
            ]);

            if ($updates) {
                $machine->update($updates);
            }

            return response()->json([
                'message' => 'Connected successfully',
                'info'    => $info,
                'machine' => $machine->fresh(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Connection failed: ' . $e->getMessage(),
            ], 502);
        }
    }

    /**
     * Pull attendance logs from the device and store new records in checkinout.
     */
    public function syncAttendance(Request $request)
    {
        $validated = $request->validate([
            'ID' => ['required', 'integer', Rule::exists('machines', 'ID')->whereNull('deleted_at')],
            'download_scope' => ['nullable', 'string', Rule::in(['today', 'date', 'all'])],
            'download_date' => ['nullable', 'date'],
            'user_filter' => ['nullable', 'string', Rule::in(['existing', 'all'])],
        ]);

        $machine = Machine::findOrFail($validated['ID']);

        if (!$machine->IP) {
            return response()->json(['message' => 'Machine has no IP address configured.'], 422);
        }

        $zk = new ZKTecoService(
            ip:       $machine->IP,
            port:     $machine->Port     ?? 4370,
            timeout:  30,
            password: blank($machine->CommPassword) ? '0' : (string) $machine->CommPassword
        );

        try {
            $zk->connect();
            $logs = $zk->getAttendanceLogs();
            $zk->disconnect();
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Sync failed: ' . $e->getMessage(),
            ], 502);
        }

        $downloadScope = $validated['download_scope'] ?? 'today';
        $downloadDate = isset($validated['download_date'])
            ? Carbon::parse($validated['download_date'])->toDateString()
            : null;
        $userFilter = $validated['user_filter'] ?? 'existing';

        $logs = array_values(array_filter($logs, function ($log) use ($downloadScope, $downloadDate) {
            $checkTime = $log['check_time'] ?? null;
            if (!$checkTime) {
                return false;
            }

            try {
                $logDate = Carbon::parse($checkTime)->toDateString();
            } catch (\Throwable) {
                return false;
            }

            return match ($downloadScope) {
                'all' => true,
                'date' => $downloadDate !== null && $logDate === $downloadDate,
                default => $logDate === Carbon::today()->toDateString(),
            };
        }));

        $imported = 0;
        $skipped  = 0;

        // Pre-fetch all valid user IDs to filter out ghost/unmapped entries
        $validUserIds = User::pluck('id')->flip()->all();

        foreach ($logs as $log) {
            $pin = trim((string) ($log['pin'] ?? ''));

            $biometric = UserBiometricInfo::query()
                ->when($pin !== '', fn ($query) => $query->where('Badgenumber', $pin))
                ->when($pin !== '' && ctype_digit($pin), function ($query) use ($pin) {
                    $query->orWhere('USERID', (int) $pin);
                })
                ->when($pin === '' && isset($log['uid']), function ($query) use ($log) {
                    $query->where('USERID', $log['uid']);
                })
                ->first();

            $resolvedUserId = $biometric?->USERID;

            if ($resolvedUserId === null && $pin !== '' && ctype_digit($pin)) {
                $resolvedUserId = (int) $pin;
            }

            if ($resolvedUserId === null && isset($log['uid']) && is_numeric($log['uid'])) {
                $resolvedUserId = (int) $log['uid'];
            }

            if ($resolvedUserId === null || $resolvedUserId <= 0) {
                $skipped++;
                continue;
            }

            // In "existing" mode, keep only logs mapped to local users.
            if ($userFilter === 'existing' && !isset($validUserIds[$resolvedUserId])) {
                $skipped++;
                continue;
            }

            $exists = Checkinout::where('USERID', $resolvedUserId)
                ->where('CHECKTIME', $log['check_time'])
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            Checkinout::create([
                'USERID'     => $resolvedUserId,
                'CHECKTIME'  => $log['check_time'],
                'CHECKTYPE'  => $log['check_type'],
                'VERIFYCODE' => $log['verify_code'],
                'Memoinfo'   => $biometric ? null : trim('UNMAPPED PIN:' . $pin . ' UID:' . ($log['uid'] ?? '')),
                'sn'         => $machine->sn,
            ]);

            $imported++;
        }

        return response()->json([
            'message'  => 'Download complete',
            'total'    => count($logs),
            'imported' => $imported,
            'skipped'  => $skipped,
            'download_scope' => $downloadScope,
            'download_date' => $downloadDate,
            'user_filter' => $userFilter,
        ]);
    }

    /**
     * Clear attendance logs stored on the biometric device.
     */
    public function clearAttendanceLogs(Request $request)
    {
        $validated = $request->validate([
            'ID' => ['required', 'integer', Rule::exists('machines', 'ID')->whereNull('deleted_at')],
        ]);

        $machine = Machine::findOrFail($validated['ID']);

        if (!$machine->IP) {
            return response()->json(['message' => 'Machine has no IP address configured.'], 422);
        }

        $zk = new ZKTecoService(
            ip: $machine->IP,
            port: $machine->Port ?? 4370,
            timeout: 20,
            password: blank($machine->CommPassword) ? '0' : (string) $machine->CommPassword
        );

        try {
            $zk->connect();
            $zk->disableDevice();
            $zk->clearAttendanceLogs();
            $zk->enableDevice();
            $zk->disconnect();
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Clear logs failed: ' . $e->getMessage(),
            ], 502);
        }

        return response()->json([
            'message' => 'Attendance logs cleared from device successfully.',
            'machine' => $machine->fresh(),
        ]);
    }

    /**
     * Push all users from the local biometric info table to one or all machines.
     * POST /api/machine/push-users
     * Body: { machine_id: int|null }   null or omitted means all enabled machines.
     */
    public function pushUsersToMachine(Request $request)
    {
        $validated = $request->validate([
            'machine_id' => [
                'nullable', 'integer',
                Rule::exists('machines', 'ID')->whereNull('deleted_at'),
            ],
        ]);

        $machines = Machine::query()
            ->whereNotNull('IP')
            ->where('Enabled', true)
            ->whereNull('deleted_at')
            ->when(
                filled($validated['machine_id'] ?? null),
                fn ($q) => $q->where('ID', $validated['machine_id'])
            )
            ->get();

        if ($machines->isEmpty()) {
            return response()->json(['message' => 'No active machines found.'], 422);
        }

        $users = UserBiometricInfo::query()
            ->join('users', 'users.id', '=', 'userinfo.USERID')
            ->select('userinfo.*', 'users.name as display_name')
            ->get();

        if ($users->isEmpty()) {
            return response()->json(['message' => 'No biometric users found.'], 422);
        }

        $results = [];

        foreach ($machines as $machine) {
            $pushed  = 0;
            $failed  = 0;
            $errors  = [];
            $templatesCopied = 0;
            $templatesAttempted = 0;
            $templatesUploaded = 0;
            $templatesFailed = 0;
            $targetMarker = $machine->MachineNumber === null ? null : (string) $machine->MachineNumber;

            try {
                $zk = new ZKTecoService(
                    ip:       $machine->IP,
                    port:     $machine->Port    ?? 4370,
                    timeout:  20,
                    password: blank($machine->CommPassword) ? '0' : (string) $machine->CommPassword
                );

                $zk->connect();
                $zk->disableDevice();

                foreach ($users as $user) {
                    $payload = [
                        'uid'         => (int) $user->USERID,
                        'badgenumber' => (string) $user->Badgenumber,
                        'name'        => $user->display_name ?? $user->Name ?? '',
                        'password'    => $user->PASSWORD ?? '',
                        'card'        => $user->CardNo ?? '',
                        'privilege'   => (int) ($user->privilege ?? 0),
                    ];

                    $templatesForDevice = $this->latestUserTemplatesByFinger((int) $user->USERID, $targetMarker);
                    $fingerTemplates = [];

                    foreach ($templatesForDevice as $row) {
                        $templateBytes = $this->extractTemplateBytes($row);

                        if ($templateBytes === null || $templateBytes === '') {
                            continue;
                        }

                        $fingerTemplates[] = [
                            'finger_id' => (int) $row->FINGERID,
                            'template' => $templateBytes,
                        ];
                    }

                    try {
                        try {
                            $zk->setUserInfo($payload);
                        } catch (\Throwable) {
                            // slot may be occupied; delete then retry.
                            $zk->deleteUserInfo((int) $user->USERID);
                            $zk->setUserInfo($payload);
                        }

                        if ($targetMarker !== null && $fingerTemplates !== []) {
                            $templatesCopied += $this->copyUserTemplatesToMachineMarker((int) $user->USERID, $targetMarker);
                        }

                        if ($fingerTemplates !== []) {
                            $templatesAttempted += count($fingerTemplates);

                            $zk->saveUserTemplates(
                                uid: (int) $user->USERID,
                                userId: (string) $payload['badgenumber'],
                                fingerTemplates: $fingerTemplates,
                                userMeta: [
                                    'name' => (string) $payload['name'],
                                    'password' => (string) ($payload['password'] ?? ''),
                                    'card' => $payload['card'] ?? 0,
                                    'privilege' => (int) ($payload['privilege'] ?? 0),
                                    'group_id' => '1',
                                ]
                            );

                            $templatesUploaded += count($fingerTemplates);
                        }

                        $pushed++;
                    } catch (\Throwable $e) {
                        $failed++;
                        if ($fingerTemplates !== []) {
                            $templatesFailed += count($fingerTemplates);
                        }
                        $errors[] = 'UID ' . $user->USERID . ': ' . $e->getMessage();
                    }
                }

                $zk->enableDevice();
                $zk->disconnect();
            } catch (\Throwable $e) {
                $results[] = [
                    'machine'  => $machine->MachineAlias ?? $machine->IP,
                    'success'  => false,
                    'message'  => $e->getMessage(),
                    'pushed'   => $pushed,
                    'failed'   => $failed,
                    'templates_copied' => $templatesCopied,
                    'templates_attempted' => $templatesAttempted,
                    'templates_uploaded' => $templatesUploaded,
                    'templates_failed' => $templatesFailed,
                ];
                continue;
            }

            $results[] = [
                'machine'  => $machine->MachineAlias ?? $machine->IP,
                'success'  => true,
                'pushed'   => $pushed,
                'failed'   => $failed,
                'templates_copied' => $templatesCopied,
                'templates_attempted' => $templatesAttempted,
                'templates_uploaded' => $templatesUploaded,
                'templates_failed' => $templatesFailed,
                'errors'   => $errors,
            ];
        }

        $totalPushed = array_sum(array_column($results, 'pushed'));
        $totalFailed = array_sum(array_column($results, 'failed'));
        $totalTemplatesCopied = array_sum(array_column($results, 'templates_copied'));
        $totalTemplatesAttempted = array_sum(array_column($results, 'templates_attempted'));
        $totalTemplatesUploaded = array_sum(array_column($results, 'templates_uploaded'));
        $totalTemplatesFailed = array_sum(array_column($results, 'templates_failed'));

        return response()->json([
            'message' => 'Push complete',
            'total_pushed' => $totalPushed,
            'total_failed' => $totalFailed,
            'total_templates_copied' => $totalTemplatesCopied,
            'total_templates_attempted' => $totalTemplatesAttempted,
            'total_templates_uploaded' => $totalTemplatesUploaded,
            'total_templates_failed' => $totalTemplatesFailed,
            'machines' => $results,
        ]);
    }

    /**
     * Push a single user record to one biometric device and optionally copy
     * saved template rows to the selected machine marker.
     */
    public function pushSingleUserToMachine(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'machine_id' => ['required', 'integer', Rule::exists('machines', 'ID')->whereNull('deleted_at')],
            'include_templates' => ['nullable', 'boolean'],
            'prepare_registration' => ['nullable', 'boolean'],
        ]);

        $user = User::query()
            ->with(['profile', 'biometricInfo'])
            ->findOrFail($validated['user_id']);

        $machine = Machine::query()->findOrFail($validated['machine_id']);

        if (!$machine->Enabled) {
            return response()->json([
                'message' => 'Selected machine is disabled.',
            ], 422);
        }

        if (!$machine->IP) {
            return response()->json([
                'message' => 'Selected machine has no IP address configured.',
            ], 422);
        }

        $biometric = $user->biometricInfo;
        $displayName = trim(implode(' ', array_filter([
            $user->profile?->first_name,
            $user->profile?->last_name,
        ]))) ?: ($user->name ?? '');

        $payload = [
            'uid' => (int) $user->id,
            'badgenumber' => (string) ($biometric?->Badgenumber ?: $user->id),
            'name' => $displayName,
            'password' => $biometric?->PASSWORD ?? '',
            'card' => $biometric?->CardNo ?? '',
            'privilege' => (int) ($biometric?->privilege ?? 0),
        ];

        $templatesCopied = 0;
        $targetMarker = $machine->MachineNumber === null ? null : (string) $machine->MachineNumber;
        $includeTemplates = (bool) ($validated['include_templates'] ?? true);
        $templatesForDevice = $includeTemplates
            ? $this->latestUserTemplatesByFinger($user->id)
            : collect();
        $templatesAttempted = 0;
        $templatesUploaded = 0;

        if ($includeTemplates && $targetMarker !== null) {
            $templatesCopied = $this->copyUserTemplatesToMachineMarker($user->id, $targetMarker);
        }

        try {
            $zk = new ZKTecoService(
                ip: $machine->IP,
                port: $machine->Port ?? 4370,
                timeout: 20,
                password: blank($machine->CommPassword) ? '0' : (string) $machine->CommPassword
            );

            $zk->connect();
            $zk->disableDevice();

            try {
                $zk->setUserInfo($payload);
            } catch (\Throwable) {
                $zk->deleteUserInfo((int) $user->id);
                $zk->setUserInfo($payload);
            }

            if ($includeTemplates && $templatesForDevice->isNotEmpty()) {
                $fingerTemplates = [];

                foreach ($templatesForDevice as $row) {
                    $templateBytes = $this->extractTemplateBytes($row);

                    if ($templateBytes === null || $templateBytes === '') {
                        continue;
                    }

                    $fingerTemplates[] = [
                        'finger_id' => (int) $row->FINGERID,
                        'template' => $templateBytes,
                    ];
                }

                $templatesAttempted = count($fingerTemplates);

                if ($templatesAttempted > 0) {
                    $zk->saveUserTemplates(
                        uid: (int) $user->id,
                        userId: (string) $payload['badgenumber'],
                        fingerTemplates: $fingerTemplates,
                        userMeta: [
                            'name' => $displayName,
                            'password' => (string) ($payload['password'] ?? ''),
                            'card' => $payload['card'] ?? 0,
                            'privilege' => (int) ($payload['privilege'] ?? 0),
                            'group_id' => '1',
                        ]
                    );

                    $templatesUploaded = $templatesAttempted;
                }
            }

            $zk->enableDevice();
            $zk->disconnect();
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Unable to push user to machine: ' . $e->getMessage(),
            ], 502);
        }

        return response()->json([
            'message' => ($validated['prepare_registration'] ?? false)
                ? 'User prepared for fingerprint registration.'
                : 'User uploaded to machine successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $displayName,
                'badge_number' => $payload['badgenumber'],
            ],
            'machine' => [
                'id' => $machine->ID,
                'name' => $machine->MachineAlias,
                'ip' => $machine->IP,
                'machine_number' => $machine->MachineNumber,
            ],
            'templates_copied' => $templatesCopied,
            'templates_upload_attempted' => $templatesAttempted,
            'templates_uploaded' => $templatesUploaded,
            'target_marker' => $targetMarker,
            'prepare_registration' => (bool) ($validated['prepare_registration'] ?? false),
            'registration_supported' => false,
            'registration_instructions' => ($validated['prepare_registration'] ?? false)
                ? 'Remote fingerprint enrollment is not supported by the current device service. Complete enrollment on the biometric device using the uploaded badge number.'
                : null,
        ]);
    }

    /**
     * Copy biometric template records from one machine marker to another.
     */
    public function syncUserTemplates(Request $request)
    {
        $validated = $request->validate([
            'source_id' => ['required', 'integer', Rule::exists('machines', 'ID')->whereNull('deleted_at')],
            'target_id' => ['required', 'integer', 'different:source_id', Rule::exists('machines', 'ID')->whereNull('deleted_at')],
        ]);

        $source = Machine::findOrFail($validated['source_id']);
        $target = Machine::findOrFail($validated['target_id']);

        $sourceMachineNumber = $source->MachineNumber;
        $targetMachineNumber = $target->MachineNumber;

        if ($sourceMachineNumber === null || $targetMachineNumber === null) {
            return response()->json([
                'message' => 'Both source and target machines must have Machine Number set.',
            ], 422);
        }

        $sourceMarker = (string) $sourceMachineNumber;
        $targetMarker = (string) $targetMachineNumber;

        $rows = BiometricTemplate::query()
            ->where('EMACHINENUM', $sourceMarker)
            ->get();

        if ($rows->isEmpty()) {
            return response()->json([
                'message' => 'No template records found for source machine.',
                'source_marker' => $sourceMarker,
                'target_marker' => $targetMarker,
                'total' => 0,
                'synced' => 0,
            ]);
        }

        $synced = 0;

        foreach ($rows as $row) {
            BiometricTemplate::updateOrCreate(
                [
                    'USERID' => $row->USERID,
                    'FINGERID' => $row->FINGERID,
                    'EMACHINENUM' => $targetMarker,
                ],
                [
                    'TEMPLATE' => $row->TEMPLATE,
                    'TEMPLATE1' => $row->TEMPLATE1,
                    'TEMPLATE2' => $row->TEMPLATE2,
                    'TEMPLATE3' => $row->TEMPLATE3,
                    'TEMPLATE4' => $row->TEMPLATE4,
                    'BITMAPPICTURE' => $row->BITMAPPICTURE,
                    'BITMAPPICTURE2' => $row->BITMAPPICTURE2,
                    'BITMAPPICTURE3' => $row->BITMAPPICTURE3,
                    'BITMAPPICTURE4' => $row->BITMAPPICTURE4,
                    'USETYPE' => $row->USETYPE,
                    'Flag' => $row->Flag,
                    'DivisionFP' => $row->DivisionFP,
                ]
            );

            $synced++;
        }

        return response()->json([
            'message' => 'Template sync complete',
            'source_marker' => $sourceMarker,
            'target_marker' => $targetMarker,
            'total' => $rows->count(),
            'synced' => $synced,
        ]);
    }

    /**
     * Push user info to a machine then trigger on-device fingerprint enrollment.
     * POST /api/machine/enroll-fingerprint
     * Body: { user_id, machine_id, finger_id (0-9) }
     */
    public function enrollFingerprint(Request $request)
    {
        $supportedFingerIds = range(0, 9);

        $fingerLabels = [
            0 => 'Left Pinky',
            1 => 'Left Ring',
            2 => 'Left Middle',
            3 => 'Left Index',
            4 => 'Left Thumb',
            5 => 'Right Thumb',
            6 => 'Right Index',
            7 => 'Right Middle',
            8 => 'Right Ring',
            9 => 'Right Pinky',
        ];

        $validated = $request->validate([
            'user_id'   => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'machine_id' => ['required', 'integer', Rule::exists('machines', 'ID')->whereNull('deleted_at')],
            'finger_id' => ['required', 'integer', 'min:0', 'max:9'],
            'local_only' => ['nullable', 'boolean'],
        ]);

        $user    = User::query()->with(['profile', 'biometricInfo'])->findOrFail($validated['user_id']);
        $machine = Machine::query()->findOrFail($validated['machine_id']);

        if (!$machine->Enabled) {
            return response()->json(['message' => 'Selected machine is disabled.'], 422);
        }

        if (!$machine->IP) {
            return response()->json(['message' => 'Selected machine has no IP address configured.'], 422);
        }

        if (!in_array((int) $validated['finger_id'], $supportedFingerIds, true)) {
            return response()->json([
                'message' => 'Selected finger is not supported by this machine firmware. Please select finger slots 0 to 8.',
                'supported_finger_ids' => $supportedFingerIds,
            ], 422);
        }

        $biometric   = $user->biometricInfo;
        $displayName = trim(implode(' ', array_filter([
            $user->profile?->first_name,
            $user->profile?->last_name,
        ]))) ?: ($user->name ?? '');

        $badgeNumber = (string) ($biometric?->Badgenumber ?: $user->id);

        $userPayload = [
            'uid'         => (int) $user->id,
            'badgenumber' => $badgeNumber,
            'name'        => $displayName,
            'password'    => $biometric?->PASSWORD ?? '',
            'card'        => $biometric?->CardNo ?? '',
            'privilege'   => (int) ($biometric?->privilege ?? 0),
        ];

        try {
            $zk = new ZKTecoService(
                ip:       $machine->IP,
                port:     $machine->Port     ?? 4370,
                timeout:  20,
                password: blank($machine->CommPassword) ? '0' : (string) $machine->CommPassword
            );

            $zk->connect();
            $zk->disableDevice();

            // Ensure the user exists on the device before starting enrollment.
            try {
                $zk->setUserInfo($userPayload);
            } catch (\Throwable) {
                $zk->deleteUserInfo((int) $user->id);
                $zk->setUserInfo($userPayload);
            }

            // Device must be enabled for the enrollment UI to respond to finger scans.
            $zk->enableDevice();

            // Some firmwares require event registration before remote enroll trigger.
            $zk->registerEvents(0xFFFF);

            $zk->startEnrollment((int) $user->id, (int) $validated['finger_id'], $badgeNumber);
            $zk->disconnect();
        } catch (\Throwable $e) {
            $status = str_contains($e->getMessage(), 'Device rejected enrollment') ? 422 : 502;

            return response()->json([
                'message' => 'Enrollment trigger failed: ' . $e->getMessage(),
            ], $status);
        }

        $fingerLabel = $fingerLabels[$validated['finger_id']] ?? 'Finger ' . $validated['finger_id'];

        return response()->json([
            'message'      => 'Fingerprint enrollment started on device.',
            'user'         => [
                'id'           => $user->id,
                'name'         => $displayName,
                'badge_number' => $badgeNumber,
            ],
            'machine'      => [
                'id'   => $machine->ID,
                'name' => $machine->MachineAlias,
                'ip'   => $machine->IP,
            ],
            'supported_finger_ids' => $supportedFingerIds,
            'finger_id'    => $validated['finger_id'],
            'finger_label' => $fingerLabel,
            'instructions' => "The device is now ready. Ask {$displayName} to place their {$fingerLabel} on the scanner when the device prompts. The scan will repeat 3 times.",
        ]);
    }

    /**
     * Check whether an enrolled finger template has been saved in local template table.
     * This is used by UI polling so the modal can stay open and allow enrolling more fingers.
     */
    public function enrollmentTemplateStatus(Request $request)
    {
        $validated = $request->validate([
            'user_id'   => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'machine_id' => ['required', 'integer', Rule::exists('machines', 'ID')->whereNull('deleted_at')],
            'finger_id' => ['required', 'integer', 'min:0', 'max:9'],
        ]);

        $machine = Machine::query()->findOrFail($validated['machine_id']);
        $targetMarker = $machine->MachineNumber === null ? null : (string) $machine->MachineNumber;

        $rows = BiometricTemplate::query()
            ->where('USERID', (int) $validated['user_id'])
            ->where('FINGERID', (int) $validated['finger_id'])
            ->orderByDesc('TEMPLATEID')
            ->get();

        if ($rows->isEmpty()) {
            $localOnly = (bool) ($validated['local_only'] ?? false);

            if ($localOnly) {
                return response()->json([
                    'found' => false,
                    'saved' => false,
                    'local_only' => true,
                    'target_marker' => $targetMarker,
                    'message' => 'Template not found in local table.',
                ]);
            }

            // Try pulling the freshly enrolled template directly from the machine.
            if ($machine->Enabled && $machine->IP) {
                try {
                    $zk = new ZKTecoService(
                        ip:       $machine->IP,
                        port:     $machine->Port     ?? 4370,
                        timeout:  20,
                        password: blank($machine->CommPassword) ? '0' : (string) $machine->CommPassword
                    );

                    $zk->connect();
                    $templateRaw = $zk->getUserTemplate((int) $validated['user_id'], (int) $validated['finger_id']);
                    $zk->disconnect();

                    if ($templateRaw !== null) {
                        if ($targetMarker !== null) {
                            BiometricTemplate::updateOrCreate(
                                [
                                    'USERID' => (int) $validated['user_id'],
                                    'FINGERID' => (int) $validated['finger_id'],
                                    'EMACHINENUM' => $targetMarker,
                                ],
                                [
                                    'TEMPLATE' => $templateRaw,
                                    'TEMPLATE4' => $templateRaw,
                                    'USETYPE' => 0,
                                    'Flag' => 1,
                                    'DivisionFP' => 10,
                                ]
                            );
                        } else {
                            $row = BiometricTemplate::query()
                                ->where('USERID', (int) $validated['user_id'])
                                ->where('FINGERID', (int) $validated['finger_id'])
                                ->whereNull('EMACHINENUM')
                                ->first();

                            if (!$row) {
                                $row = new BiometricTemplate([
                                    'USERID' => (int) $validated['user_id'],
                                    'FINGERID' => (int) $validated['finger_id'],
                                    'EMACHINENUM' => null,
                                ]);
                            }

                            $row->TEMPLATE = $templateRaw;
                            $row->TEMPLATE4 = $templateRaw;
                            $row->USETYPE = 0;
                            $row->Flag = 1;
                            $row->DivisionFP = 10;
                            $row->save();
                        }

                        $rows = BiometricTemplate::query()
                            ->where('USERID', (int) $validated['user_id'])
                            ->where('FINGERID', (int) $validated['finger_id'])
                            ->orderByDesc('TEMPLATEID')
                            ->get();
                    }
                } catch (\Throwable) {
                    // Keep polling behavior resilient; if pull fails, caller can retry.
                }
            }

            if ($rows->isEmpty()) {
                return response()->json([
                    'found' => false,
                    'saved' => false,
                    'target_marker' => $targetMarker,
                    'message' => 'Template not found yet. Continue scanning on the device.',
                ]);
            }

            return response()->json([
                'found' => true,
                'saved' => true,
                'saved_from_device' => true,
                'target_marker' => $targetMarker,
                'message' => 'Template pulled from device and saved in local template table.',
            ]);
        }

        $selected = $targetMarker !== null
            ? ($rows->firstWhere('EMACHINENUM', $targetMarker) ?? $rows->first())
            : $rows->first();

        // If template exists but not in target marker, copy it so later uploads can use this machine marker.
        $savedToTarget = false;
        if ($targetMarker !== null && (string) $selected->EMACHINENUM !== $targetMarker) {
            BiometricTemplate::updateOrCreate(
                [
                    'USERID' => $selected->USERID,
                    'FINGERID' => $selected->FINGERID,
                    'EMACHINENUM' => $targetMarker,
                ],
                [
                    'TEMPLATE' => $selected->TEMPLATE,
                    'TEMPLATE1' => $selected->TEMPLATE1,
                    'TEMPLATE2' => $selected->TEMPLATE2,
                    'TEMPLATE3' => $selected->TEMPLATE3,
                    'TEMPLATE4' => $selected->TEMPLATE4,
                    'BITMAPPICTURE' => $selected->BITMAPPICTURE,
                    'BITMAPPICTURE2' => $selected->BITMAPPICTURE2,
                    'BITMAPPICTURE3' => $selected->BITMAPPICTURE3,
                    'BITMAPPICTURE4' => $selected->BITMAPPICTURE4,
                    'USETYPE' => $selected->USETYPE,
                    'Flag' => $selected->Flag,
                    'DivisionFP' => $selected->DivisionFP,
                ]
            );

            $savedToTarget = true;
            $selected = BiometricTemplate::query()
                ->where('USERID', (int) $validated['user_id'])
                ->where('FINGERID', (int) $validated['finger_id'])
                ->where('EMACHINENUM', $targetMarker)
                ->first() ?? $selected;
        }

        return response()->json([
            'found' => true,
            'saved' => true,
            'saved_to_target_marker' => $savedToTarget,
            'target_marker' => $targetMarker,
            'template' => [
                'template_id' => $selected->TEMPLATEID,
                'user_id' => $selected->USERID,
                'finger_id' => $selected->FINGERID,
                'machine_marker' => $selected->EMACHINENUM,
            ],
            'message' => 'Template saved in local template table.',
        ]);
    }

    private function copyUserTemplatesToMachineMarker(int $userId, string $targetMarker): int
    {
        $rows = $this->latestUserTemplatesByFinger($userId, $targetMarker);

        $copied = 0;

        foreach ($rows as $row) {
            BiometricTemplate::updateOrCreate(
                [
                    'USERID' => $row->USERID,
                    'FINGERID' => $row->FINGERID,
                    'EMACHINENUM' => $targetMarker,
                ],
                [
                    'TEMPLATE' => $row->TEMPLATE,
                    'TEMPLATE1' => $row->TEMPLATE1,
                    'TEMPLATE2' => $row->TEMPLATE2,
                    'TEMPLATE3' => $row->TEMPLATE3,
                    'TEMPLATE4' => $row->TEMPLATE4,
                    'BITMAPPICTURE' => $row->BITMAPPICTURE,
                    'BITMAPPICTURE2' => $row->BITMAPPICTURE2,
                    'BITMAPPICTURE3' => $row->BITMAPPICTURE3,
                    'BITMAPPICTURE4' => $row->BITMAPPICTURE4,
                    'USETYPE' => $row->USETYPE,
                    'Flag' => $row->Flag,
                    'DivisionFP' => $row->DivisionFP,
                ]
            );

            $copied++;
        }

        return $copied;
    }

    private function latestUserTemplatesByFinger(int $userId, ?string $preferOtherThanMarker = null)
    {
        return BiometricTemplate::query()
            ->where('USERID', $userId)
            ->orderByDesc('TEMPLATEID')
            ->get()
            ->groupBy('FINGERID')
            ->map(function ($templates) use ($preferOtherThanMarker) {
                if ($preferOtherThanMarker !== null) {
                    return $templates->firstWhere('EMACHINENUM', '!=', $preferOtherThanMarker) ?? $templates->first();
                }

                return $templates->first();
            })
            ->filter();
    }

    private function extractTemplateBytes(BiometricTemplate $row): ?string
    {
        if (!empty($row->TEMPLATE)) {
            return $row->TEMPLATE;
        }

        $chunks = [];

        foreach (['TEMPLATE1', 'TEMPLATE2', 'TEMPLATE3', 'TEMPLATE4'] as $field) {
            if (!empty($row->{$field})) {
                $chunks[] = $row->{$field};
            }
        }

        if ($chunks === []) {
            return null;
        }

        return implode('', $chunks);
    }

    private function rules(?int $ignoreId = null): array
    {
        $snRule = Rule::unique('machines', 'sn')->whereNull('deleted_at');
        $ipRule = Rule::unique('machines', 'IP')->whereNull('deleted_at');

        if ($ignoreId) {
            $snRule = $snRule->ignore($ignoreId, 'ID');
            $ipRule = $ipRule->ignore($ignoreId, 'ID');
        }

        return [
            'MachineAlias' => ['required', 'string', 'max:255'],
            'ConnectType' => ['nullable', 'string', 'max:50'],
            'IP' => ['nullable', 'string', 'max:45', $ipRule],
            'SerialPort' => ['nullable', 'string', 'max:50'],
            'Port' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'Baudrate' => ['nullable', 'integer', 'min:0'],
            'MachineNumber' => ['nullable', 'integer', 'min:0'],
            'IsHost' => ['nullable', 'boolean'],
            'Enabled' => ['nullable', 'boolean'],
            'CommPassword' => ['nullable', 'string', 'max:100'],
            'UILanguage' => ['nullable', 'string', 'max:50'],
            'DateFormat' => ['nullable', 'string', 'max:50'],
            'InOutRecordWarn' => ['nullable', 'integer', 'min:0'],
            'Idle' => ['nullable', 'integer', 'min:0'],
            'Voice' => ['nullable', 'integer', 'min:0'],
            'managercount' => ['nullable', 'integer', 'min:0'],
            'usercount' => ['nullable', 'integer', 'min:0'],
            'fingercount' => ['nullable', 'integer', 'min:0'],
            'SecretCount' => ['nullable', 'integer', 'min:0'],
            'FirmwareVersion' => ['nullable', 'string', 'max:100'],
            'ProductType' => ['nullable', 'string', 'max:100'],
            'LockControl' => ['nullable', 'string', 'max:50'],
            'Purpose' => ['nullable', 'string', 'max:100'],
            'ProduceKind' => ['nullable', 'string', 'max:100'],
            'sn' => ['nullable', 'string', 'max:100', $snRule],
            'PhotoStamp' => ['nullable', 'boolean'],
            'IsIfChangeConfigServer2' => ['nullable', 'boolean'],
            'pushver' => ['nullable', 'string', 'max:50'],
            'IsAndroid' => ['nullable', 'boolean'],
            'AutoDownload' => ['nullable', 'boolean'],
            'AutoDownloadInterval' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'AutoDownloadUserFilter' => ['nullable', 'string', Rule::in(['existing', 'all'])],
        ];
    }
}

