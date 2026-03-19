<?php

namespace App\Console\Commands;

use App\Models\Checkinout;
use App\Models\Machine;
use App\Models\User;
use App\Models\UserBiometricInfo;
use App\Services\ZKTecoService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AutoSyncAttendance extends Command
{
    protected $signature = 'attendance:auto-sync';

    protected $description = 'Download attendance logs in the background for machines with AutoDownload enabled';

    public function handle(): int
    {
        $machines = Machine::query()
            ->where('Enabled', true)
            ->where('AutoDownload', true)
            ->whereNotNull('IP')
            ->whereNull('deleted_at')
            ->orderBy('ID')
            ->get();

        if ($machines->isEmpty()) {
            $this->line('No machines with AutoDownload enabled.');
            return self::SUCCESS;
        }

        $today = Carbon::today()->toDateString();
        $validUserIds = User::pluck('id')->flip()->all();

        $totalImported = 0;
        $totalSkipped = 0;
        $totalMachinesOk = 0;
        $now = Carbon::now();

        foreach ($machines as $machine) {
            $intervalSeconds = max(1, (int) ($machine->AutoDownloadInterval ?? 60));
            $userFilter = $machine->AutoDownloadUserFilter === 'all' ? 'all' : 'existing';
            $lastSyncedAt = $machine->AutoDownloadLastSyncedAt;

            if ($lastSyncedAt && $lastSyncedAt->diffInSeconds($now) < $intervalSeconds) {
                continue;
            }

            try {
                $zk = new ZKTecoService(
                    ip: $machine->IP,
                    port: $machine->Port ?? 4370,
                    timeout: 20,
                    password: blank($machine->CommPassword) ? '0' : (string) $machine->CommPassword
                );

                $zk->connect();
                $logs = $zk->getAttendanceLogs();
                $zk->disconnect();
            } catch (\Throwable $e) {
                $machine->update([
                    'AutoDownloadLastSyncedAt' => Carbon::now(),
                ]);
                $this->warn('Machine ' . ($machine->MachineAlias ?: $machine->IP) . ' failed: ' . $e->getMessage());
                continue;
            }

            $imported = 0;
            $skipped = 0;

            foreach ($logs as $log) {
                $checkTime = $log['check_time'] ?? null;
                if (!$checkTime) {
                    $skipped++;
                    continue;
                }

                try {
                    $logDate = Carbon::parse($checkTime)->toDateString();
                } catch (\Throwable) {
                    $skipped++;
                    continue;
                }

                // Keep background sync lightweight by importing only current-day punches.
                if ($logDate !== $today) {
                    continue;
                }

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

                if ($userFilter === 'existing' && !isset($validUserIds[$resolvedUserId])) {
                    $skipped++;
                    continue;
                }

                $exists = Checkinout::query()
                    ->where('USERID', $resolvedUserId)
                    ->where('CHECKTIME', $checkTime)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                Checkinout::create([
                    'USERID' => $resolvedUserId,
                    'CHECKTIME' => $checkTime,
                    'CHECKTYPE' => $log['check_type'] ?? 'I',
                    'VERIFYCODE' => $log['verify_code'] ?? 0,
                    'Memoinfo' => $biometric ? null : trim('UNMAPPED PIN:' . $pin . ' UID:' . ($log['uid'] ?? '')),
                    'sn' => $machine->sn,
                ]);

                $imported++;
            }

            $totalMachinesOk++;
            $totalImported += $imported;
            $totalSkipped += $skipped;

            $machine->update([
                'AutoDownloadLastSyncedAt' => Carbon::now(),
            ]);

            $this->line('Machine ' . ($machine->MachineAlias ?: $machine->IP) . ': imported=' . $imported . ', skipped=' . $skipped);
        }

        $this->info('Auto sync done. Machines OK: ' . $totalMachinesOk . ', imported: ' . $totalImported . ', skipped: ' . $totalSkipped);

        return self::SUCCESS;
    }
}
