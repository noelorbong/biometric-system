<?php

namespace App\Http\Controllers;

use App\Models\BiometricTemplate;
use App\Models\Checkinout;
use App\Models\Machine;
use App\Models\User;
use App\Models\UserBiometricInfo;
use App\Models\UserProfile;
use App\Services\ZKTecoService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
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
                'ProductType'     => $info['DeviceName']   ?? ($info['Platform'] ?? null),
                'ProduceKind'     => $info['ProduceKind']  ?? ($info['Platform'] ?? null),
                'pushver'         => $info['Platform']     ?? null,
                'Purpose'         => $info['WorkCode']     ?? null,
                'managercount'    => isset($info['ManagerCount']) && is_numeric($info['ManagerCount']) ? (int) $info['ManagerCount'] : null,
                'usercount'       => isset($info['UserCount']) && is_numeric($info['UserCount']) ? (int) $info['UserCount'] : null,
                'fingercount'     => isset($info['FPCount']) && is_numeric($info['FPCount']) ? (int) $info['FPCount'] : null,
                'SecretCount'     => isset($info['FaceCount']) && is_numeric($info['FaceCount']) ? (int) $info['FaceCount'] : null,
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
     * Download users stored on a biometric device and import matching local biometric rows.
     */
    public function downloadUsers(Request $request)
    {
        @set_time_limit(0);

        $validated = $request->validate([
            'ID' => ['nullable', 'integer', Rule::exists('machines', 'ID')->whereNull('deleted_at')],
            'ip' => ['nullable', 'ip', 'required_without:ID'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'password' => ['nullable', 'string'],
            'preview_only' => ['nullable', 'boolean'],
            'include_templates' => ['nullable', 'boolean'],
            'preview_rows' => ['nullable', 'array'],
            'selected_user_ids' => ['nullable', 'array'],
            'selected_user_ids.*' => ['integer', 'min:1'],
            'include_unmatched' => ['nullable', 'boolean'],
        ]);

        $previewOnly = (bool) ($validated['preview_only'] ?? false);
        $machine = isset($validated['ID']) ? Machine::findOrFail($validated['ID']) : null;
        $machineIp = $machine?->IP ?? ($validated['ip'] ?? null);
        $machinePort = $machine?->Port ?? ($validated['port'] ?? 4370);
        $machinePassword = $machine?->CommPassword ?? ($validated['password'] ?? '0');
        $progressKey = $this->downloadUsersProgressKey(
            $machine?->ID,
            (int) auth()->id(),
            $machineIp
        );

        if (!$machineIp) {
            return response()->json(['message' => 'Machine has no IP address configured.'], 422);
        }

        if ($previewOnly) {
            Cache::forget($progressKey);
        } else {
            $this->putDownloadUsersProgress($progressKey, [
                'state' => 'running',
                'phase' => 'fetching_device_users',
                'total' => 0,
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'created_users' => 0,
                'restored_users' => 0,
                'templates_downloaded' => 0,
                'templates_created' => 0,
                'templates_updated' => 0,
                'templates_failed' => 0,
                'templates_enabled' => (bool) ($validated['include_templates'] ?? false),
                'message' => 'Fetching device users...',
            ]);
        }

        $zk = new ZKTecoService(
            ip: $machineIp,
            port: $machinePort,
            timeout: 20,
            password: blank($machinePassword) ? '0' : (string) $machinePassword
        );

        $deviceUsers = [];
        $usePreviewRows = !$previewOnly && is_array($validated['preview_rows'] ?? null) && ($validated['preview_rows'] ?? []) !== [];

        if ($usePreviewRows) {
            $deviceUsers = array_values(array_map(static function (array $row): array {
                return [
                    'uid' => (int) ($row['uid'] ?? 0),
                    'pin' => (string) ($row['pin'] ?? ''),
                    'name' => (string) ($row['name'] ?? ''),
                    'password' => (string) ($row['password'] ?? ''),
                    'privilege' => (int) ($row['privilege'] ?? 0),
                    'card' => (int) ($row['card'] ?? 0),
                ];
            }, $validated['preview_rows'] ?? []));
        } else {
            try {
                $zk->connect();
                $deviceInfo = [];

                try {
                    $deviceInfo = $zk->getDeviceInfo();
                } catch (\Throwable) {
                    $deviceInfo = [];
                }

                $zk->configureUserDecodeProfile(
                    $deviceInfo['FirmVer'] ?? $machine?->FirmwareVersion,
                    $deviceInfo['DeviceName'] ?? $machine?->ProductType,
                    $deviceInfo['ProduceKind'] ?? $machine?->ProduceKind
                );

                $deviceUsers = $zk->getUsers();
                $deviceUsers = $this->normalizeConflictedDevicePins($deviceUsers);
                $zk->disconnect();
            } catch (\Throwable $e) {
                return response()->json([
                    'message' => 'User download failed: ' . $e->getMessage(),
                ], 502);
            }
        }

        if ($deviceUsers === []) {
            return response()->json([
                'message' => $previewOnly ? 'No users found on device preview.' : 'No users found on device.',
                'preview_only' => $previewOnly,
                'total_device_users' => 0,
                'planned_create' => 0,
                'planned_update' => 0,
                'planned_restore' => 0,
                'created' => 0,
                'updated' => 0,
                'created_users' => 0,
                'restored_users' => 0,
                'templates_downloaded' => 0,
                'templates_created' => 0,
                'templates_updated' => 0,
                'templates_failed' => 0,
                'skipped_unmatched' => 0,
                'skipped_invalid' => 0,
                'conflict_count' => 0,
                'conflicts' => [],
                'device_users' => [],
                'unmatched_users' => [],
            ]);
        }

        $activeUserIds = User::query()->pluck('id')->flip()->all();
        $softDeletedUserIds = User::onlyTrashed()->pluck('id')->flip()->all();
        $existingBiometricIds = UserBiometricInfo::withTrashed()->pluck('USERID')->flip()->all();
        $previewRows = array_map(
            fn (array $deviceUser) => $this->buildDeviceUserPreviewRow($deviceUser, $activeUserIds, $softDeletedUserIds, $existingBiometricIds),
            $deviceUsers
        );
        $conflicts = $this->detectDeviceUserConflicts($previewRows);

        $plannedCreate = count(array_filter($previewRows, fn (array $row) => $row['planned_action'] === 'create'));
        $plannedUpdate = count(array_filter($previewRows, fn (array $row) => $row['planned_action'] === 'update'));
        $plannedRestore = count(array_filter($previewRows, fn (array $row) => $row['planned_action'] === 'restore'));
        $skippedInvalid = count(array_filter($previewRows, fn (array $row) => $row['planned_action'] === 'invalid'));
        $unmatchedUsers = array_values(array_map(
            fn (array $row) => [
                'uid' => $row['resolved_user_id'],
                'pin' => $row['pin'],
                'name' => $row['name'],
            ],
            array_filter($previewRows, fn (array $row) => $row['planned_action'] === 'unmatched')
        ));

        if ($previewOnly) {
            return response()->json([
                'message' => 'Device user preview generated.',
                'preview_only' => true,
                'total_device_users' => count($deviceUsers),
                'planned_create' => $plannedCreate,
                'planned_update' => $plannedUpdate,
                'planned_restore' => $plannedRestore,
                'skipped_unmatched' => count($unmatchedUsers),
                'skipped_invalid' => $skippedInvalid,
                'conflict_count' => count($conflicts),
                'conflicts' => array_slice($conflicts, 0, 200),
                'device_users' => $previewRows,
                'unmatched_users' => array_slice($unmatchedUsers, 0, 10),
            ]);
        }

        $selectedUserIds = array_values(array_unique(array_map('intval', $validated['selected_user_ids'] ?? [])));
        $includeUnmatched = (bool) ($validated['include_unmatched'] ?? false);
        $includeTemplates = (bool) ($validated['include_templates'] ?? false);
        $rowsForImport = $previewRows;

        if ($selectedUserIds !== []) {
            $selectedLookup = array_flip($selectedUserIds);
            $rowsForImport = array_values(array_filter($previewRows, function (array $row) use ($selectedLookup) {
                $resolved = (int) ($row['resolved_user_id'] ?? 0);

                return $resolved > 0 && isset($selectedLookup[$resolved]);
            }));
        }

        $rowsToProcess = array_values(array_filter($rowsForImport, function (array $row) use ($includeUnmatched): bool {
            return in_array($row['planned_action'], ['create', 'update', 'restore'], true)
                || ($includeUnmatched && $row['planned_action'] === 'unmatched');
        }));

        $progressTotal = count($rowsToProcess);
        $processed = 0;

        if (!$previewOnly) {
            $this->putDownloadUsersProgress($progressKey, [
                'state' => 'running',
                'phase' => 'importing',
                'started_at' => now()->toIso8601String(),
                'total' => $progressTotal,
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'created_users' => 0,
                'restored_users' => 0,
                'templates_downloaded' => 0,
                'templates_created' => 0,
                'templates_updated' => 0,
                'templates_failed' => 0,
                'templates_enabled' => $includeTemplates,
                'message' => $progressTotal > 0 ? 'Starting import...' : 'No selected users to import.',
            ]);
        }

        $created = 0;
        $updated = 0;
        $createdUsers = 0;
        $restoredUsers = 0;
        $templatesDownloaded = 0;
        $templatesCreated = 0;
        $templatesUpdated = 0;
        $templatesFailed = 0;
        $templateMachineMarker = $machine?->MachineNumber === null ? null : (string) $machine->MachineNumber;

        $templateSessionReady = false;

        if ($includeTemplates && $rowsForImport !== []) {
            try {
                $zk->connect();
                $zk->disableDevice();
                $templateSessionReady = true;
            } catch (\Throwable) {
                $templateSessionReady = false;
            }
        }

        $importError = null;

        try {
            foreach ($rowsToProcess as $previewRow) {
            $resolvedUserId = (int) $previewRow['resolved_user_id'];

            if ($resolvedUserId <= 0) {
                $processed++;
                continue;
            }

            if ($previewRow['planned_action'] === 'restore') {
                $softDeletedUser = User::withTrashed()->find($resolvedUserId);

                if ($softDeletedUser && $softDeletedUser->trashed()) {
                    $softDeletedUser->restore();
                    $restoredUsers++;
                }
            }

            if ($previewRow['planned_action'] === 'unmatched') {
                $existingUser = User::withTrashed()->find($resolvedUserId);

                if ($existingUser) {
                    if ($existingUser->trashed()) {
                        $existingUser->restore();
                        $restoredUsers++;
                    }
                } else {
                    $newUser = new User([
                        'name' => (string) ($previewRow['name'] ?: ('Imported User ' . $resolvedUserId)),
                        'email' => $this->buildImportedUserEmail($resolvedUserId, (string) ($previewRow['pin'] ?? '')),
                        'password' => Str::random(16),
                        'role' => 0,
                        'status' => true,
                    ]);

                    $newUser->id = $resolvedUserId;
                    $newUser->save();
                    $createdUsers++;
                }
            }

            // Upsert UserProfile with parsed name components for every imported user.
            $deviceName = (string) ($previewRow['name'] ?? '');
            if ($deviceName !== '') {
                $nameParts = $this->parseDeviceName($deviceName);
                UserProfile::updateOrCreate(
                    ['user_id' => $resolvedUserId],
                    array_filter([
                        'first_name'  => $nameParts['first_name']  ?: null,
                        'last_name'   => $nameParts['last_name']   ?: null,
                        'middle_name' => $nameParts['middle_name'] ?: null,
                    ], fn ($v) => $v !== null)
                );
            }

            $payload = $this->buildBiometricPayload($previewRow, $resolvedUserId);
            $record = UserBiometricInfo::withTrashed()->where('USERID', $resolvedUserId)->first();

            if ($record) {
                if ($record->trashed()) {
                    $record->restore();
                }

                $record->update($payload);
                $updated++;
            } else {
                UserBiometricInfo::create($payload);
                $created++;
            }

            if ($includeTemplates && $templateSessionReady) {
                $templateStats = $this->downloadAndStoreUserTemplates(
                    $zk,
                    $resolvedUserId,
                    $templateMachineMarker
                );

                $templatesDownloaded += $templateStats['downloaded'];
                $templatesCreated += $templateStats['created'];
                $templatesUpdated += $templateStats['updated'];
                $templatesFailed += $templateStats['failed'];
            }

            $processed++;

            // Update progress every 5 rows and on completion.
            if ($processed % 5 === 0 || $processed === $progressTotal) {
                $this->putDownloadUsersProgress($progressKey, [
                    'state' => 'running',
                    'phase' => 'importing',
                    'total' => $progressTotal,
                    'processed' => $processed,
                    'created' => $created,
                    'updated' => $updated,
                    'created_users' => $createdUsers,
                    'restored_users' => $restoredUsers,
                    'templates_downloaded' => $templatesDownloaded,
                    'templates_created' => $templatesCreated,
                    'templates_updated' => $templatesUpdated,
                    'templates_failed' => $templatesFailed,
                    'templates_enabled' => $includeTemplates,
                    'message' => "Imported {$processed} of {$progressTotal} users",
                ]);
            }
        }
        } catch (\Throwable $e) {
            $importError = $e;

            $this->putDownloadUsersProgress($progressKey, [
                'state' => 'failed',
                'phase' => 'importing',
                'total' => $progressTotal,
                'processed' => $processed,
                'created' => $created,
                'updated' => $updated,
                'created_users' => $createdUsers,
                'restored_users' => $restoredUsers,
                'templates_downloaded' => $templatesDownloaded,
                'templates_created' => $templatesCreated,
                'templates_updated' => $templatesUpdated,
                'templates_failed' => $templatesFailed,
                'templates_enabled' => $includeTemplates,
                'message' => $e->getMessage(),
            ]);
        }

        if ($templateSessionReady) {
            try {
                $zk->enableDevice();
                $zk->disconnect();
            } catch (\Throwable) {
                // keep user import result resilient if template session cleanup fails
            }
        }

        if ($importError instanceof \Throwable) {
            return response()->json([
                'message' => 'User import failed: ' . $importError->getMessage(),
            ], 500);
        }

        $response = [
            'message' => 'User download complete.',
            'preview_only' => false,
            'total_device_users' => count($deviceUsers),
            'planned_create' => $plannedCreate,
            'planned_update' => $plannedUpdate,
            'planned_restore' => $plannedRestore,
            'created' => $created,
            'updated' => $updated,
            'created_users' => $createdUsers,
            'restored_users' => $restoredUsers,
            'templates_downloaded' => $templatesDownloaded,
            'templates_created' => $templatesCreated,
            'templates_updated' => $templatesUpdated,
            'templates_failed' => $templatesFailed,
            'templates_enabled' => $includeTemplates,
            'skipped_unmatched' => count($unmatchedUsers),
            'skipped_invalid' => $skippedInvalid,
            'conflict_count' => count($conflicts),
            'conflicts' => array_slice($conflicts, 0, 200),
            'device_users' => $previewRows,
            'unmatched_users' => array_slice($unmatchedUsers, 0, 10),
        ];

        $this->putDownloadUsersProgress($progressKey, [
            'state' => 'completed',
            'phase' => 'done',
            'total' => $progressTotal,
            'processed' => $processed,
            'created' => $created,
            'updated' => $updated,
            'created_users' => $createdUsers,
            'restored_users' => $restoredUsers,
            'templates_downloaded' => $templatesDownloaded,
            'templates_created' => $templatesCreated,
            'templates_updated' => $templatesUpdated,
            'templates_failed' => $templatesFailed,
            'templates_enabled' => $includeTemplates,
            'message' => 'User import complete.',
        ]);

        return response()->json($response);
    }

    public function downloadUsersProgress(Request $request)
    {
        $validated = $request->validate([
            'ID' => ['nullable', 'integer', Rule::exists('machines', 'ID')->whereNull('deleted_at')],
            'ip' => ['nullable', 'ip', 'required_without:ID'],
        ]);

        $machine = isset($validated['ID']) ? Machine::find($validated['ID']) : null;
        $machineIp = $machine?->IP ?? ($validated['ip'] ?? null);
        $key = $this->downloadUsersProgressKey($machine?->ID, (int) auth()->id(), $machineIp);
        $progress = Cache::get($key);

        if (!is_array($progress)) {
            $progress = [
                'state' => 'idle',
                'phase' => 'idle',
                'total' => 0,
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'created_users' => 0,
                'restored_users' => 0,
                'templates_downloaded' => 0,
                'templates_created' => 0,
                'templates_updated' => 0,
                'templates_failed' => 0,
                'templates_enabled' => false,
                'message' => 'No active import.',
            ];
        }

        return response()->json($progress);
    }

    private function downloadUsersProgressKey(?int $machineId, int $userId, ?string $machineIp = null): string
    {
        if ($machineId !== null && $machineId > 0) {
            return "machine:download-users:progress:user:{$userId}:machine:{$machineId}";
        }

        $hash = md5((string) ($machineIp ?? 'unknown'));
        return "machine:download-users:progress:user:{$userId}:ip:{$hash}";
    }

    /**
     * @param array<string, mixed> $state
     */
    private function putDownloadUsersProgress(string $key, array $state): void
    {
        $existing = Cache::get($key);
        if (is_array($existing) && !isset($state['started_at']) && isset($existing['started_at'])) {
            $state['started_at'] = $existing['started_at'];
        }

        if (!isset($state['started_at'])) {
            $state['started_at'] = now()->toIso8601String();
        }

        $state['updated_at'] = now()->toIso8601String();
        Cache::put($key, $state, now()->addMinutes(30));
    }

    /**
     * Detect conflicting device rows, mainly duplicate PINs mapped to multiple
     * names or resolved IDs.
     *
     * @param array<int, array<string, mixed>> $previewRows
     * @return array<int, array<string, mixed>>
     */
    private function detectDeviceUserConflicts(array $previewRows): array
    {
        $byPin = [];

        foreach ($previewRows as $row) {
            $pin = trim((string) ($row['pin'] ?? ''));

            if ($pin === '') {
                continue;
            }

            $byPin[$pin][] = [
                'uid' => (int) ($row['uid'] ?? 0),
                'resolved_user_id' => (int) ($row['resolved_user_id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'planned_action' => (string) ($row['planned_action'] ?? ''),
            ];
        }

        $conflicts = [];

        foreach ($byPin as $pin => $rows) {
            if (count($rows) <= 1) {
                continue;
            }

            $distinctNames = [];
            $distinctResolvedIds = [];

            foreach ($rows as $r) {
                $nameKey = strtolower(trim((string) ($r['name'] ?? '')));
                if ($nameKey !== '') {
                    $distinctNames[$nameKey] = true;
                }

                $resolvedId = (int) ($r['resolved_user_id'] ?? 0);
                if ($resolvedId > 0) {
                    $distinctResolvedIds[(string) $resolvedId] = true;
                }
            }

            $isConflict = count($distinctNames) > 1 || count($distinctResolvedIds) > 1;

            if (!$isConflict) {
                continue;
            }

            $conflicts[] = [
                'type' => 'duplicate_pin',
                'pin' => $pin,
                'count' => count($rows),
                'rows' => $rows,
            ];
        }

        usort($conflicts, static fn (array $a, array $b): int => ($b['count'] ?? 0) <=> ($a['count'] ?? 0));

        return $conflicts;
    }

    /**
     * Decode-oriented repair for duplicate numeric PIN rows seen on some firmware:
     * one row is valid while another row carries a trailing artifact digit.
     *
     * @param array<int, array<string, mixed>> $deviceUsers
     * @return array<int, array<string, mixed>>
     */
    private function normalizeConflictedDevicePins(array $deviceUsers): array
    {
        $groups = [];

        foreach ($deviceUsers as $idx => $row) {
            $pin = trim((string) ($row['pin'] ?? ''));
            if ($pin !== '' && ctype_digit($pin)) {
                $groups[$pin][] = $idx;
            }
        }

        foreach ($groups as $pin => $indexes) {
            if (count($indexes) < 2 || strlen($pin) < 2) {
                continue;
            }

            $suspicious = [];
            $normal = [];

            foreach ($indexes as $idx) {
                $password = (string) ($deviceUsers[$idx]['password'] ?? '');
                if ($password !== '' && preg_match('/[^\x20-\x7E]/', $password) === 1) {
                    $suspicious[] = $idx;
                } else {
                    $normal[] = $idx;
                }
            }

            // If exactly one row in this duplicate pin group looks garbled,
            // treat it as a decode artifact and trim the trailing artifact digit.
            if (count($suspicious) !== 1 || $normal === []) {
                continue;
            }

            $idx = $suspicious[0];
            $trimmed = ltrim(substr($pin, 0, -1), '0');
            if ($trimmed === '') {
                $trimmed = '0';
            }

            $deviceUsers[$idx]['pin'] = $trimmed;

            $currentUid = (int) ($deviceUsers[$idx]['uid'] ?? 0);
            if ($currentUid <= 0 || (string) $currentUid === (string) $pin) {
                $deviceUsers[$idx]['uid'] = (int) $trimmed;
            }
        }

        return $deviceUsers;
    }

    /**
     * Build a preview row describing how one device user would map into local records.
     *
     * @param array{uid?:mixed,pin?:mixed,name?:mixed,password?:mixed,privilege?:mixed,card?:mixed} $deviceUser
     * @param array<int, mixed> $activeUserIds
     * @param array<int, mixed> $softDeletedUserIds
     * @param array<int, mixed> $existingBiometricIds
     * @return array<string, mixed>
     */
    private function buildDeviceUserPreviewRow(
        array $deviceUser,
        array $activeUserIds,
        array $softDeletedUserIds,
        array $existingBiometricIds
    ): array
    {
        $rawUid = (int) ($deviceUser['uid'] ?? 0);
        $pin = trim((string) ($deviceUser['pin'] ?? ''));
        $name = trim((string) ($deviceUser['name'] ?? ''));
        $card = (int) ($deviceUser['card'] ?? 0);

        $resolvedUserId = $rawUid;

        if ($resolvedUserId <= 0 && $pin !== '' && ctype_digit($pin)) {
            $resolvedUserId = (int) $pin;
        }

        if ($resolvedUserId <= 0 && $pin !== '') {
            $pinDigits = preg_replace('/\D+/', '', $pin) ?? '';
            if ($pinDigits !== '' && ctype_digit($pinDigits)) {
                $resolvedUserId = (int) $pinDigits;
            }
        }

        if ($resolvedUserId <= 0 && $card > 0) {
            $resolvedUserId = $card;
        }

        if ($resolvedUserId <= 0 && ($pin !== '' || $name !== '')) {
            // Keep generated IDs inside signed INT range while avoiding common real IDs.
            $seed = $pin !== '' ? $pin : $name;
            $resolvedUserId = 1900000000 + (crc32($seed) % 100000000);
        }

        $plannedAction = 'invalid';
        $statusLabel = 'Invalid';

        if ($resolvedUserId > 0) {
            if (isset($softDeletedUserIds[$resolvedUserId])) {
                $plannedAction = 'restore';
                $statusLabel = 'Restore';
            } elseif (!isset($activeUserIds[$resolvedUserId])) {
                $plannedAction = 'unmatched';
                $statusLabel = 'Unmatched';
            } elseif (isset($existingBiometricIds[$resolvedUserId])) {
                $plannedAction = 'update';
                $statusLabel = 'Update';
            } else {
                $plannedAction = 'create';
                $statusLabel = 'Create';
            }
        }

        return [
            'uid' => $rawUid > 0 ? $rawUid : $resolvedUserId,
            'resolved_user_id' => $resolvedUserId,
            'pin' => $pin,
            'name' => $name,
            'password' => (string) ($deviceUser['password'] ?? ''),
            'privilege' => (int) ($deviceUser['privilege'] ?? 0),
            'card' => $card,
            'planned_action' => $plannedAction,
            'status_label' => $statusLabel,
        ];
    }

    /**
     * Build the local biometric payload for a matched device user.
     *
     * @param array<string, mixed> $deviceUser
     * @return array<string, mixed>
     */
    private function buildBiometricPayload(array $deviceUser, int $resolvedUserId): array
    {
        $payload = [
            'USERID' => $resolvedUserId,
            'Badgenumber' => (string) (($deviceUser['pin'] ?? '') !== '' ? $deviceUser['pin'] : $resolvedUserId),
            'PASSWORD' => (string) ($deviceUser['password'] ?? ''),
            'privilege' => (int) ($deviceUser['privilege'] ?? 0),
            'CardNo' => (int) ($deviceUser['card'] ?? 0) > 0 ? (string) $deviceUser['card'] : null,
        ];

        if ((string) ($deviceUser['name'] ?? '') !== '') {
            $payload['Name'] = (string) $deviceUser['name'];
        }

        return $payload;
    }

    private function buildImportedUserEmail(int $resolvedUserId, string $pin): string
    {
        $seed = ctype_digit($pin) ? $pin : (string) $resolvedUserId;
        $base = 'device' . $seed;
        $candidate = $base . '@biometric.local';
        $suffix = 1;

        while (User::withTrashed()->where('email', $candidate)->exists()) {
            $candidate = $base . '+' . $suffix . '@biometric.local';
            $suffix++;
        }

        return $candidate;
    }

    /**
     * Parse a device name (e.g. "ABAIGAR, APRIL D.") into name components.
     *
     * Supported formats (case-insensitive input, output is title-cased):
     *   "LAST, FIRST MIDDLE"   → last=Last  first=First  middle=Middle
     *   "LAST, FIRST M."       → last=Last  first=First  middle=M
     *   "LAST, FIRST"          → last=Last  first=First  middle=''
     *   "FIRST LAST"           → last=Last  first=First  middle=''
     *
     * @return array{first_name:string,last_name:string,middle_name:string}
     */
    private function parseDeviceName(string $name): array
    {
        $name = trim($name);

        if ($name === '') {
            return ['first_name' => '', 'last_name' => '', 'middle_name' => ''];
        }

        // Title-case each whitespace-separated word individually.
        $toTitle = static function (string $segment): string {
            return implode(' ', array_map(
                static fn (string $w) => mb_strtoupper(mb_substr($w, 0, 1)) . mb_strtolower(mb_substr($w, 1)),
                array_filter(explode(' ', $segment))
            ));
        };

        // Format: "LASTNAME, FIRSTNAME [MIDDLE...]"
        if (str_contains($name, ',')) {
            [$rawLast, $rawRest] = explode(',', $name, 2);
            $last  = $toTitle(trim($rawLast));
            $parts = array_values(array_filter(explode(' ', trim($rawRest))));

            $first  = isset($parts[0]) ? $toTitle($parts[0]) : '';
            $middle = '';

            if (count($parts) > 1) {
                // Combine remaining parts; strip trailing period from bare initials ("D." → "D")
                $middleParts = array_slice($parts, 1);
                $middle = implode(' ', array_map(static function (string $p) use ($toTitle): string {
                    $p = rtrim($p, '.');
                    return $p !== '' ? $toTitle($p) : '';
                }, $middleParts));
                $middle = trim($middle);
            }

            return ['first_name' => $first, 'last_name' => $last, 'middle_name' => $middle];
        }

        // Format: "FIRSTNAME LASTNAME" (no comma — fall back to simple split)
        $parts = array_values(array_filter(explode(' ', $name)));
        if (count($parts) === 1) {
            return ['first_name' => $toTitle($parts[0]), 'last_name' => '', 'middle_name' => ''];
        }

        $first = $toTitle(array_shift($parts));
        $last  = $toTitle(array_pop($parts));
        $middle = implode(' ', array_map($toTitle, $parts));

        return ['first_name' => $first, 'last_name' => $last, 'middle_name' => $middle];
    }

    private function downloadAndStoreUserTemplates(ZKTecoService $zk, int $userId, ?string $machineMarker): array
    {
        $stats = [
            'downloaded' => 0,
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
        ];

        foreach (range(0, 9) as $fingerId) {
            try {
                $templateRaw = $zk->getUserTemplate($userId, $fingerId);
            } catch (\Throwable) {
                $stats['failed']++;
                continue;
            }

            if ($templateRaw === null || $templateRaw === '') {
                continue;
            }

            $result = $this->upsertDownloadedTemplate($userId, $fingerId, $templateRaw, $machineMarker);
            $stats['downloaded']++;

            if ($result === 'created') {
                $stats['created']++;
            } else {
                $stats['updated']++;
            }
        }

        return $stats;
    }

    private function upsertDownloadedTemplate(int $userId, int $fingerId, string $templateRaw, ?string $machineMarker): string
    {
        $query = BiometricTemplate::query()
            ->where('USERID', $userId)
            ->where('FINGERID', $fingerId);

        if ($machineMarker === null) {
            $query->whereNull('EMACHINENUM');
        } else {
            $query->where('EMACHINENUM', $machineMarker);
        }

        $row = $query->first();

        if (!$row) {
            $row = new BiometricTemplate([
                'USERID' => $userId,
                'FINGERID' => $fingerId,
                'EMACHINENUM' => $machineMarker,
            ]);
            $result = 'created';
        } else {
            $result = 'updated';
        }

        $row->TEMPLATE = $templateRaw;
        $row->TEMPLATE4 = $templateRaw;
        $row->USETYPE = 0;
        $row->Flag = 1;
        $row->DivisionFP = 10;
        $row->save();

        return $result;
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
        // $displayName = trim(implode(' ', array_filter([
        //     $user->profile?->first_name,
        //     $user->profile?->last_name,
        // ]))) ?: ($user->name ?? '');

        $displayName = ($user->name ?? '');

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
     * Push user info to a machine then trigger on-device face enrollment.
     * POST /api/machine/enroll-face
     * Body: { user_id, machine_id }
     */
    public function enrollFace(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'machine_id' => ['required', 'integer', Rule::exists('machines', 'ID')->whereNull('deleted_at')],
        ]);

        $user = User::query()->with(['profile', 'biometricInfo'])->findOrFail($validated['user_id']);
        $machine = Machine::query()->findOrFail($validated['machine_id']);

        if (!$machine->Enabled) {
            return response()->json(['message' => 'Selected machine is disabled.'], 422);
        }

        if (!$machine->IP) {
            return response()->json(['message' => 'Selected machine has no IP address configured.'], 422);
        }

        $biometric = $user->biometricInfo;
        $displayName = trim(implode(' ', array_filter([
            $user->profile?->first_name,
            $user->profile?->last_name,
        ]))) ?: ($user->name ?? '');

        $badgeNumber = (string) ($biometric?->Badgenumber ?: $user->id);

        $userPayload = [
            'uid' => (int) $user->id,
            'badgenumber' => $badgeNumber,
            'name' => $displayName,
            'password' => $biometric?->PASSWORD ?? '',
            'card' => $biometric?->CardNo ?? '',
            'privilege' => (int) ($biometric?->privilege ?? 0),
        ];

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
                $zk->setUserInfo($userPayload);
            } catch (\Throwable) {
                $zk->deleteUserInfo((int) $user->id);
                $zk->setUserInfo($userPayload);
            }

            $zk->enableDevice();
            $zk->registerEvents(0xFFFF);
            $zk->startFaceEnrollment((int) $user->id, $badgeNumber);
            $zk->disconnect();
        } catch (\Throwable $e) {
            $status = str_contains($e->getMessage(), 'rejected face enrollment') ? 422 : 502;

            return response()->json([
                'message' => 'Face enrollment trigger failed: ' . $e->getMessage(),
            ], $status);
        }

        return response()->json([
            'message' => 'Face enrollment started on device.',
            'user' => [
                'id' => $user->id,
                'name' => $displayName,
                'badge_number' => $badgeNumber,
            ],
            'machine' => [
                'id' => $machine->ID,
                'name' => $machine->MachineAlias,
                'ip' => $machine->IP,
            ],
            'instructions' => "The device is ready. Ask {$displayName} to face the camera and complete the on-screen face capture prompts.",
        ]);
    }

    /**
     * Check whether a face template has been saved in local template table.
     * If missing, optionally tries pulling from device and then saves it locally.
     */
    public function enrollmentFaceStatus(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'machine_id' => ['required', 'integer', Rule::exists('machines', 'ID')->whereNull('deleted_at')],
            'local_only' => ['nullable', 'boolean'],
            'debug' => ['nullable', 'boolean'],
        ]);

        $machine = Machine::query()->findOrFail($validated['machine_id']);
        $targetMarker = $machine->MachineNumber === null ? null : (string) $machine->MachineNumber;
        $faceSlots = $this->faceTemplateBackupCandidates();
        $debugEnabled = (bool) ($validated['debug'] ?? false);
        $debugData = [
            'user_id' => (int) $validated['user_id'],
            'machine_id' => (int) $validated['machine_id'],
            'local_only' => (bool) ($validated['local_only'] ?? false),
            'machine_enabled' => (bool) $machine->Enabled,
            'machine_ip' => $machine->IP,
            'target_marker' => $targetMarker,
            'candidate_slot_count' => count($faceSlots),
            'attempted_slots' => [],
            'found_slot' => null,
            'pulled_from_device' => false,
            'device_pull_error' => null,
            'local_rows_before' => 0,
            'local_rows_after' => 0,
        ];

        $rows = BiometricTemplate::query()
            ->where('USERID', (int) $validated['user_id'])
            ->where(function ($query) use ($faceSlots) {
                $query->whereIn('FINGERID', $faceSlots)
                    ->orWhere('FINGERID', '>', 9);
            })
            ->orderByDesc('TEMPLATEID')
            ->get();

        $debugData['local_rows_before'] = $rows->count();

        if ($rows->isEmpty()) {
            $localOnly = (bool) ($validated['local_only'] ?? false);

            if ($localOnly) {
                $response = [
                    'found' => false,
                    'saved' => false,
                    'local_only' => true,
                    'target_marker' => $targetMarker,
                    'message' => 'Face template not found in local table.',
                ];

                if ($debugEnabled) {
                    $response['debug'] = $debugData;
                }

                return response()->json($response);
            }

            if ($machine->Enabled && $machine->IP) {
                try {
                    $zk = new ZKTecoService(
                        ip: $machine->IP,
                        port: $machine->Port ?? 4370,
                        timeout: 20,
                        password: blank($machine->CommPassword) ? '0' : (string) $machine->CommPassword
                    );

                    $zk->connect();
                    $foundFromDevice = false;

                    foreach ($faceSlots as $slot) {
                        $debugData['attempted_slots'][] = $slot;
                        $templateRaw = $zk->getUserTemplateByBackupNumber((int) $validated['user_id'], (int) $slot);

                        if ($templateRaw === null || $templateRaw === '') {
                            continue;
                        }

                        $this->upsertDownloadedTemplate((int) $validated['user_id'], (int) $slot, $templateRaw, $targetMarker);
                        $foundFromDevice = true;
                        $debugData['found_slot'] = $slot;
                        break;
                    }

                    $debugData['pulled_from_device'] = $foundFromDevice;

                    $zk->disconnect();

                    if ($foundFromDevice) {
                        $rows = BiometricTemplate::query()
                            ->where('USERID', (int) $validated['user_id'])
                            ->where(function ($query) use ($faceSlots) {
                                $query->whereIn('FINGERID', $faceSlots)
                                    ->orWhere('FINGERID', '>', 9);
                            })
                            ->orderByDesc('TEMPLATEID')
                            ->get();
                    }
                } catch (\Throwable $e) {
                    $debugData['device_pull_error'] = $e->getMessage();
                    // Keep polling behavior resilient; caller can retry.
                }
            }

            $debugData['local_rows_after'] = $rows->count();

            if ($rows->isEmpty()) {
                $response = [
                    'found' => false,
                    'saved' => false,
                    'target_marker' => $targetMarker,
                    'face_slots' => $faceSlots,
                    'message' => 'Face template not found yet. Continue face capture on the device.',
                ];

                if ($debugEnabled) {
                    $response['debug'] = $debugData;
                }

                return response()->json($response);
            }
        }

        $selected = $targetMarker !== null
            ? ($rows->firstWhere('EMACHINENUM', $targetMarker) ?? $rows->first())
            : $rows->first();

        $response = [
            'found' => true,
            'saved' => true,
            'target_marker' => $targetMarker,
            'face_slots' => $faceSlots,
            'template' => [
                'template_id' => $selected->TEMPLATEID,
                'user_id' => $selected->USERID,
                'backup_number' => $selected->FINGERID,
                'machine_marker' => $selected->EMACHINENUM,
            ],
            'message' => 'Face template saved in local template table.',
        ];

        if ($debugEnabled) {
            $debugData['local_rows_after'] = max($debugData['local_rows_after'], 1);
            $response['debug'] = $debugData;
        }

        return response()->json($response);
    }

    private function faceTemplateBackupCandidates(): array
    {
        return array_values(array_unique(array_merge(
            range(10, 29),
            range(50, 59),
            range(70, 79),
            [97, 98, 99],
            range(110, 129)
        )));
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

