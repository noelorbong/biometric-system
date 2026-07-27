<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AppSetting;
use App\Services\DatabaseBackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\Process\Process;

class AppSettingController extends Controller
{
    private const DEFAULT_SETTINGS = [
        'company_school_name' => 'Biometric System',
        'company_school_logo' => '',
        'company_school_logo_print_enabled' => false,
        'biometric_dtr_signatory_name' => 'In-Charge',
        'biometric_dtr_signatory_signature' => '',
        'biometric_dtr_signatory_use_default' => true,
        'biometric_dtr_signatory_signature_enabled' => false,
        'machine_auto_sync_status_timer_enabled' => true,
        'machine_auto_sync_status_timer_ms' => 5000,
        'machine_refresh_timer_enabled' => true,
        'machine_refresh_timer_ms' => 5000,
        'machine_web_auto_fallback_timer_enabled' => true,
        'machine_web_auto_fallback_timer_ms' => 1000,
        'backup_auto_enabled' => false,
        'backup_auto_schedule' => 'daily',
        'backup_auto_time' => '02:00',
        'backup_auto_retain_days' => 7,
    ];

    private const BACKUP_SCHEDULE_OPTIONS = [
        'daily',
        'weekly',
        'hourly',
        'everyTwelveHours',
    ];

    private function normalizeIntSetting(mixed $value, int $default): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (!is_numeric($value)) {
            return $default;
        }

        return (int) $value;
    }

    private function getSettingsMap()
    {
        return AppSetting::query()
            ->orderBy('setting_key')
            ->pluck('setting_value', 'setting_key');
    }

    private function upsertSettings(array $pairs, ?int $userId): void
    {
        foreach ($pairs as $key => $value) {
            $setting = AppSetting::withTrashed()
                ->where('setting_key', $key)
                ->first();

            if ($setting) {
                if ($setting->trashed()) {
                    $setting->restore();
                }

                $setting->update([
                    'setting_value' => $value,
                    'user_last_modify' => $userId,
                ]);

                continue;
            }

            AppSetting::create([
                'setting_key' => $key,
                'setting_value' => $value,
                'user_add' => $userId,
                'user_last_modify' => $userId,
            ]);
        }
    }

    private function resolveGeneralSettings($settings): array
    {
        return [
            'company_school_name' => $settings['company_school_name'] ?? self::DEFAULT_SETTINGS['company_school_name'],
            'company_school_logo' => $settings['company_school_logo'] ?? self::DEFAULT_SETTINGS['company_school_logo'],
            'company_school_logo_print_enabled' => filter_var(
                $settings['company_school_logo_print_enabled'] ?? self::DEFAULT_SETTINGS['company_school_logo_print_enabled'],
                FILTER_VALIDATE_BOOLEAN
            ),
            'biometric_dtr_signatory_name' => (string) ($settings['biometric_dtr_signatory_name'] ?? self::DEFAULT_SETTINGS['biometric_dtr_signatory_name']),
            'biometric_dtr_signatory_signature' => (string) ($settings['biometric_dtr_signatory_signature'] ?? self::DEFAULT_SETTINGS['biometric_dtr_signatory_signature']),
            'biometric_dtr_signatory_use_default' => filter_var(
                $settings['biometric_dtr_signatory_use_default'] ?? self::DEFAULT_SETTINGS['biometric_dtr_signatory_use_default'],
                FILTER_VALIDATE_BOOLEAN
            ),
            'biometric_dtr_signatory_signature_enabled' => filter_var(
                $settings['biometric_dtr_signatory_signature_enabled'] ?? self::DEFAULT_SETTINGS['biometric_dtr_signatory_signature_enabled'],
                FILTER_VALIDATE_BOOLEAN
            ),
            'machine_auto_sync_status_timer_enabled' => filter_var(
                $settings['machine_auto_sync_status_timer_enabled'] ?? self::DEFAULT_SETTINGS['machine_auto_sync_status_timer_enabled'],
                FILTER_VALIDATE_BOOLEAN
            ),
            'machine_auto_sync_status_timer_ms' => $this->normalizeIntSetting(
                $settings['machine_auto_sync_status_timer_ms'] ?? self::DEFAULT_SETTINGS['machine_auto_sync_status_timer_ms'],
                self::DEFAULT_SETTINGS['machine_auto_sync_status_timer_ms']
            ),
            'machine_refresh_timer_enabled' => filter_var(
                $settings['machine_refresh_timer_enabled'] ?? self::DEFAULT_SETTINGS['machine_refresh_timer_enabled'],
                FILTER_VALIDATE_BOOLEAN
            ),
            'machine_refresh_timer_ms' => $this->normalizeIntSetting(
                $settings['machine_refresh_timer_ms'] ?? self::DEFAULT_SETTINGS['machine_refresh_timer_ms'],
                self::DEFAULT_SETTINGS['machine_refresh_timer_ms']
            ),
            'machine_web_auto_fallback_timer_enabled' => filter_var(
                $settings['machine_web_auto_fallback_timer_enabled'] ?? self::DEFAULT_SETTINGS['machine_web_auto_fallback_timer_enabled'],
                FILTER_VALIDATE_BOOLEAN
            ),
            'machine_web_auto_fallback_timer_ms' => $this->normalizeIntSetting(
                $settings['machine_web_auto_fallback_timer_ms'] ?? self::DEFAULT_SETTINGS['machine_web_auto_fallback_timer_ms'],
                self::DEFAULT_SETTINGS['machine_web_auto_fallback_timer_ms']
            ),
        ];
    }

    private function resolveAutoBackupSettings($settings): array
    {
        $schedule = (string) ($settings['backup_auto_schedule'] ?? self::DEFAULT_SETTINGS['backup_auto_schedule']);

        if (!in_array($schedule, self::BACKUP_SCHEDULE_OPTIONS, true)) {
            $schedule = self::DEFAULT_SETTINGS['backup_auto_schedule'];
        }

        return [
            'enabled' => filter_var(
                $settings['backup_auto_enabled'] ?? self::DEFAULT_SETTINGS['backup_auto_enabled'],
                FILTER_VALIDATE_BOOLEAN
            ),
            'schedule' => $schedule,
            'time' => (string) ($settings['backup_auto_time'] ?? self::DEFAULT_SETTINGS['backup_auto_time']),
            'retain_days' => $this->normalizeIntSetting(
                $settings['backup_auto_retain_days'] ?? self::DEFAULT_SETTINGS['backup_auto_retain_days'],
                self::DEFAULT_SETTINGS['backup_auto_retain_days']
            ),
            'passphrase_configured' => filled($settings['backup_auto_passphrase_encrypted'] ?? null),
        ];
    }

    private function resolveBackupFilePath(string $filename): string
    {
        $safeFilename = trim($filename);

        if ($safeFilename === '' || str_contains($safeFilename, '..') || str_contains($safeFilename, '/') || str_contains($safeFilename, '\\')) {
            throw new \RuntimeException('Invalid backup filename.');
        }

        if (!str_ends_with($safeFilename, '.bkp')) {
            throw new \RuntimeException('Only .bkp files are allowed.');
        }

        return 'backups/' . $safeFilename;
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

    public function index(Request $request)
    {
        if (!$this->isSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $settings = $this->getSettingsMap();
        $resolved = $this->resolveGeneralSettings($settings);

        return response()->json([
            'settings' => $resolved,
        ]);
    }

    public function update(Request $request)
    {
        if (!$this->isSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'company_school_name' => ['required', 'string', 'max:255'],
            'company_school_logo' => ['nullable', 'string', 'max:255'],
            'company_school_logo_print_enabled' => ['required', 'boolean'],
            'biometric_dtr_signatory_name' => ['nullable', 'string', 'max:255'],
            'biometric_dtr_signatory_signature' => ['nullable', 'string', 'max:255'],
            'biometric_dtr_signatory_use_default' => ['required', 'boolean'],
            'biometric_dtr_signatory_signature_enabled' => ['required', 'boolean'],
            'machine_auto_sync_status_timer_enabled' => ['required', 'boolean'],
            'machine_auto_sync_status_timer_ms' => ['nullable', 'integer', 'min:250', 'max:300000'],
            'machine_refresh_timer_enabled' => ['required', 'boolean'],
            'machine_refresh_timer_ms' => ['nullable', 'integer', 'min:250', 'max:300000'],
            'machine_web_auto_fallback_timer_enabled' => ['required', 'boolean'],
            'machine_web_auto_fallback_timer_ms' => ['nullable', 'integer', 'min:250', 'max:300000'],
        ]);

        $pairs = [
            'company_school_name' => $validated['company_school_name'],
            'company_school_logo' => (string) ($validated['company_school_logo'] ?? ''),
            'company_school_logo_print_enabled' => $validated['company_school_logo_print_enabled'] ? '1' : '0',
            'biometric_dtr_signatory_name' => (string) ($validated['biometric_dtr_signatory_name'] ?? ''),
            'biometric_dtr_signatory_signature' => (string) ($validated['biometric_dtr_signatory_signature'] ?? ''),
            'biometric_dtr_signatory_use_default' => $validated['biometric_dtr_signatory_use_default'] ? '1' : '0',
            'biometric_dtr_signatory_signature_enabled' => $validated['biometric_dtr_signatory_signature_enabled'] ? '1' : '0',
            'machine_auto_sync_status_timer_enabled' => $validated['machine_auto_sync_status_timer_enabled'] ? '1' : '0',
            'machine_auto_sync_status_timer_ms' => (string) ($validated['machine_auto_sync_status_timer_ms'] ?? self::DEFAULT_SETTINGS['machine_auto_sync_status_timer_ms']),
            'machine_refresh_timer_enabled' => $validated['machine_refresh_timer_enabled'] ? '1' : '0',
            'machine_refresh_timer_ms' => (string) ($validated['machine_refresh_timer_ms'] ?? self::DEFAULT_SETTINGS['machine_refresh_timer_ms']),
            'machine_web_auto_fallback_timer_enabled' => $validated['machine_web_auto_fallback_timer_enabled'] ? '1' : '0',
            'machine_web_auto_fallback_timer_ms' => (string) ($validated['machine_web_auto_fallback_timer_ms'] ?? self::DEFAULT_SETTINGS['machine_web_auto_fallback_timer_ms']),
        ];

        $this->upsertSettings($pairs, $request->user()?->id);

        $settings = $this->getSettingsMap();
        $resolved = $this->resolveGeneralSettings($settings);

        return response()->json([
            'message' => 'Success',
            'settings' => $resolved,
        ]);
    }

    public function runMaintenancePatch(Request $request)
    {
        if (!$this->isSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $commands = [
            'storage:link',
            'config:clear',
            'cache:clear',
            'route:clear',
            'view:clear',
            'migrate --force',
        ];

        $results = [];

        foreach ($commands as $command) {
            try {
                $exitCode = Artisan::call($command);
                $output = trim(Artisan::output());

                $results[] = [
                    'command' => $command,
                    'success' => $exitCode === 0,
                    'exit_code' => $exitCode,
                    'output' => $output,
                ];
            } catch (\Throwable $exception) {
                $results[] = [
                    'command' => $command,
                    'success' => false,
                    'exit_code' => 1,
                    'output' => $exception->getMessage(),
                ];
            }
        }

        $allSuccessful = collect($results)->every(fn ($item) => (bool) ($item['success'] ?? false));

        return response()->json([
            'message' => $allSuccessful ? 'Maintenance patch completed' : 'Maintenance patch completed with issues',
            'success' => $allSuccessful,
            'commands' => $results,
        ], $allSuccessful ? 200 : 207);
    }

    public function runSystemUpdate(Request $request)
    {
        if (!$this->isSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $results = [];

        try {
            $gitProcess = Process::fromShellCommandline('git pull origin main', base_path());
            $gitProcess->setTimeout(300);
            $gitProcess->run();

            $results[] = [
                'command' => 'git pull origin main',
                'success' => $gitProcess->isSuccessful(),
                'exit_code' => $gitProcess->getExitCode(),
                'output' => trim($gitProcess->getOutput() . PHP_EOL . $gitProcess->getErrorOutput()),
            ];
        } catch (\Throwable $exception) {
            $results[] = [
                'command' => 'git pull origin main',
                'success' => false,
                'exit_code' => 1,
                'output' => $exception->getMessage(),
            ];
        }

        $allSuccessful = collect($results)->every(fn ($item) => (bool) ($item['success'] ?? false));

        return response()->json([
            'message' => $allSuccessful ? 'System update completed' : 'System update completed with issues',
            'success' => $allSuccessful,
            'commands' => $results,
        ], $allSuccessful ? 200 : 207);
    }

    private function runShellCommand(string $command, ?string $workingDirectory = null, int $timeoutSeconds = 120): array
    {
        $process = Process::fromShellCommandline($command, $workingDirectory ?? base_path());
        $process->setTimeout($timeoutSeconds);
        $process->run();

        return [
            'success' => $process->isSuccessful(),
            'exit_code' => $process->getExitCode(),
            'output' => trim($process->getOutput() . PHP_EOL . $process->getErrorOutput()),
        ];
    }

    public function attendanceDaemonStatus(Request $request)
    {
        if (!$this->isSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $isLinux = DIRECTORY_SEPARATOR === '/';
        $configPath = '/etc/supervisor/conf.d/attendance-auto-sync.conf';

        if (!$isLinux) {
            return response()->json([
                'available' => false,
                'message' => 'Supervisor status is only available on Linux servers.',
            ]);
        }

        $supervisorctl = $this->runShellCommand('command -v supervisorctl >/dev/null 2>&1 && echo OK || echo MISSING');
        $supervisorInstalled = str_contains($supervisorctl['output'] ?? '', 'OK');

        $statusOutput = '';
        $running = false;
        $statusExitCode = null;

        if ($supervisorInstalled) {
            $status = $this->runShellCommand('supervisorctl status attendance-auto-sync');
            if (!($status['success'] ?? false)) {
                $status = $this->runShellCommand('sudo -n supervisorctl status attendance-auto-sync');
            }

            $statusOutput = (string) ($status['output'] ?? '');
            $statusExitCode = $status['exit_code'] ?? null;
            $running = preg_match('/\bRUNNING\b/i', $statusOutput) === 1;
        }

        return response()->json([
            'available' => true,
            'os' => php_uname('s'),
            'config_path' => $configPath,
            'config_exists' => is_file($configPath),
            'supervisor_installed' => $supervisorInstalled,
            'service_running' => $running,
            'status_output' => $statusOutput,
            'status_exit_code' => $statusExitCode,
            'config_writable' => is_writable(dirname($configPath)),
            'php_binary' => PHP_BINARY,
            'app_path' => base_path(),
        ]);
    }

    public function installAttendanceDaemon(Request $request)
    {
        if (!$this->isSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $isLinux = DIRECTORY_SEPARATOR === '/';
        if (!$isLinux) {
            return response()->json([
                'message' => 'Installation is only supported on Linux servers.',
            ], 422);
        }

        $validated = $request->validate([
            'sleep' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $sleep = (int) ($validated['sleep'] ?? 1);
        $configPath = '/etc/supervisor/conf.d/attendance-auto-sync.conf';

        // Prefer CLI php binary instead of PHP_BINARY from web/FPM context.
        $resolvedPhpBinary = '/usr/bin/php';
        $phpLookup = $this->runShellCommand('command -v php');
        if (($phpLookup['success'] ?? false) && filled($phpLookup['output'] ?? null)) {
            $candidate = trim(explode(PHP_EOL, (string) $phpLookup['output'])[0]);
            if ($candidate !== '' && is_executable($candidate)) {
                $resolvedPhpBinary = $candidate;
            }
        }

        if (!is_executable($resolvedPhpBinary)) {
            return response()->json([
                'message' => 'Unable to resolve a valid CLI php binary for supervisor command.',
                'php_binary' => $resolvedPhpBinary,
            ], 422);
        }

        $daemonUser = 'www-data';
        if (function_exists('posix_getpwuid')) {
            $ownerInfo = @posix_getpwuid(@fileowner(base_path()));
            if (is_array($ownerInfo) && filled($ownerInfo['name'] ?? null)) {
                $daemonUser = (string) $ownerInfo['name'];
            }
        }

        $config = implode(PHP_EOL, [
            '[program:attendance-auto-sync]',
            'command=' . $resolvedPhpBinary . ' ' . base_path('artisan') . ' attendance:auto-sync:daemon --sleep=' . $sleep,
            'directory=' . base_path(),
            'autostart=true',
            'autorestart=true',
            'startsecs=3',
            'stopasgroup=true',
            'killasgroup=true',
            'user=' . $daemonUser,
            'stdout_logfile=' . storage_path('logs/attendance-auto-sync.log'),
            'stderr_logfile=' . storage_path('logs/attendance-auto-sync-error.log'),
            '',
        ]);

        $tmpPath = storage_path('app/private/attendance-auto-sync.conf.tmp');
        @mkdir(dirname($tmpPath), 0755, true);
        file_put_contents($tmpPath, $config);

        $steps = [];
        $copyCommand = 'cp ' . escapeshellarg($tmpPath) . ' ' . escapeshellarg($configPath);

        if (is_writable(dirname($configPath))) {
            $copy = $this->runShellCommand($copyCommand);
        } else {
            $copy = $this->runShellCommand('sudo -n ' . $copyCommand);
            if (!$copy['success']) {
                return response()->json([
                    'message' => 'Unable to write supervisor config. Grant permission or allow sudo without password for web user.',
                    'step' => 'write_config',
                    'output' => $copy['output'] ?? '',
                ], 422);
            }
        }

        $steps[] = array_merge(['command' => 'install config'], $copy);

        $runWithSudoFallback = function (string $command) use (&$steps) {
            $result = $this->runShellCommand($command);
            if ($result['success']) {
                $steps[] = array_merge(['command' => $command], $result);
                return $result;
            }

            $sudoResult = $this->runShellCommand('sudo -n ' . $command);
            $steps[] = array_merge(['command' => $command], $sudoResult);
            return $sudoResult;
        };

        foreach (['supervisorctl reread', 'supervisorctl update'] as $command) {
            $result = $runWithSudoFallback($command);
            if (!$result['success']) {
                return response()->json([
                    'message' => 'Supervisor command failed. Check supervisor installation and sudo permissions.',
                    'step' => $command,
                    'commands' => $steps,
                ], 422);
            }
        }

        // Try restart first; if service is new/not running yet, fall back to start.
        $restart = $runWithSudoFallback('supervisorctl restart attendance-auto-sync');
        if (!$restart['success']) {
            $start = $runWithSudoFallback('supervisorctl start attendance-auto-sync');
            if (!$start['success']) {
                return response()->json([
                    'message' => 'Supervisor command failed. Check supervisor installation and sudo permissions.',
                    'step' => 'supervisorctl start attendance-auto-sync',
                    'commands' => $steps,
                ], 422);
            }
        }

        $status = $runWithSudoFallback('supervisorctl status attendance-auto-sync');
        if (!$status['success']) {
            return response()->json([
                'message' => 'Supervisor command failed. Check supervisor installation and sudo permissions.',
                'step' => 'supervisorctl status attendance-auto-sync',
                'commands' => $steps,
            ], 422);
        }

        ActivityLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'Installed Attendance Auto Sync Daemon',
            'model_name' => AppSetting::class,
            'model_id' => 0,
            'before' => null,
            'after' => [
                'config_path' => $configPath,
                'sleep' => $sleep,
                'user' => $daemonUser,
                'php_binary' => $resolvedPhpBinary,
            ],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Attendance auto-sync daemon installed and started.',
            'config_path' => $configPath,
            'php_binary' => $resolvedPhpBinary,
            'commands' => $steps,
        ]);
    }

    public function backupDatabase(Request $request, DatabaseBackupService $backupService)
    {
        if (!$this->isSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'passphrase' => ['required', 'string', 'min:8', 'max:128'],
        ]);

        try {
            $snapshot = $backupService->buildSnapshot();
            $encrypted = $backupService->encryptSnapshot($snapshot, (string) $validated['passphrase']);

            $timestamp = now()->utc()->format('Ymd_His');
            $filename = "db-backup-{$timestamp}.bkp";
            $path = "backups/{$filename}";

            Storage::disk('local')->put($path, $encrypted);

            return response()->json([
                'message' => 'Encrypted database backup created.',
                'filename' => $filename,
                'path' => $path,
                'size_bytes' => strlen($encrypted),
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => 'Unable to create backup.',
                'error' => $exception->getMessage(),
            ], 422);
        }
    }

    public function exportDatabase(Request $request, DatabaseBackupService $backupService)
    {
        if (!$this->isSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'passphrase' => ['required', 'string', 'min:8', 'max:128'],
        ]);

        try {
            $snapshot = $backupService->buildSnapshot();
            $encrypted = $backupService->encryptSnapshot($snapshot, (string) $validated['passphrase']);
            $filename = 'db-export-' . now()->utc()->format('Ymd_His') . '.bkp';

            return response($encrypted, 200, [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'X-Backup-Format' => 'biometric-system-db-backup',
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => 'Unable to export backup.',
                'error' => $exception->getMessage(),
            ], 422);
        }
    }

    public function importDatabase(Request $request, DatabaseBackupService $backupService)
    {
        if (!$this->isSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'passphrase' => ['required', 'string', 'min:8', 'max:128'],
            'backup_file' => ['required', 'file', 'max:512000'],
        ]);

        try {
            $content = file_get_contents($validated['backup_file']->getRealPath());
            if ($content === false) {
                throw new \RuntimeException('Unable to read uploaded backup file.');
            }

            $snapshot = $backupService->decryptSnapshot($content, (string) $validated['passphrase']);
            $result = $backupService->restoreSnapshot($snapshot);

            return response()->json([
                'message' => 'Database import completed.',
                'restored_tables' => $result['restored_tables'],
                'missing_tables' => $result['missing_tables'],
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => 'Unable to import backup.',
                'error' => $exception->getMessage(),
            ], 422);
        }
    }

    public function backupAutoStatus(Request $request)
    {
        if (!$this->isSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $settings = $this->getSettingsMap();
        $resolved = $this->resolveAutoBackupSettings($settings);

        $files = collect(Storage::disk('local')->files('backups'))
            ->filter(fn (string $f) => str_ends_with($f, '.bkp'))
            ->map(function (string $path) {
                $filename = basename($path);
                try {
                    $lastModified = Storage::disk('local')->lastModified($path);
                } catch (\Throwable) {
                    $lastModified = 0;
                }
                $sizeBytes = Storage::disk('local')->size($path);

                return [
                    'filename'    => $filename,
                    'path'        => $path,
                    'size_bytes'  => $sizeBytes,
                    'size_kb'     => round($sizeBytes / 1024, 1),
                    'modified_at' => $lastModified
                        ? now()->setTimestamp($lastModified)->utc()->toIso8601String()
                        : null,
                ];
            })
            ->sortByDesc('modified_at')
            ->values()
            ->all();

        return response()->json([
            'auto_backup' => $resolved,
            'backups' => $files,
        ]);
    }

    public function updateAutoBackup(Request $request)
    {
        if (!$this->isSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'schedule' => ['required', Rule::in(self::BACKUP_SCHEDULE_OPTIONS)],
            'time' => ['required', 'date_format:H:i'],
            'retain_days' => ['required', 'integer', 'min:1', 'max:365'],
            'passphrase' => ['nullable', 'string', 'min:8', 'max:128'],
        ]);

        $settings = $this->getSettingsMap();
        $current = $this->resolveAutoBackupSettings($settings);
        $before = $current;
        $passphrase = trim((string) ($validated['passphrase'] ?? ''));

        if ($validated['enabled'] && $passphrase === '' && !$current['passphrase_configured']) {
            return response()->json([
                'message' => 'A backup passphrase is required before enabling automatic backup.',
                'errors' => [
                    'passphrase' => ['A backup passphrase is required before enabling automatic backup.'],
                ],
            ], 422);
        }

        $pairs = [
            'backup_auto_enabled' => $validated['enabled'] ? '1' : '0',
            'backup_auto_schedule' => (string) $validated['schedule'],
            'backup_auto_time' => (string) $validated['time'],
            'backup_auto_retain_days' => (string) $validated['retain_days'],
        ];

        if ($passphrase !== '') {
            $pairs['backup_auto_passphrase_encrypted'] = Crypt::encryptString($passphrase);
        }

        $this->upsertSettings($pairs, $request->user()?->id);

        $after = $this->resolveAutoBackupSettings($this->getSettingsMap());

        ActivityLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'Updated Auto Backup Settings',
            'model_name' => AppSetting::class,
            'model_id' => 0,
            'before' => $before,
            'after' => $after,
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Automatic backup settings updated.',
            'auto_backup' => $after,
        ]);
    }

    public function runAutoBackupNow(Request $request)
    {
        if (!$this->isSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        try {
            $exitCode = Artisan::call('db:auto-backup');
            $output = trim(Artisan::output());

            ActivityLog::create([
                'user_id' => $request->user()?->id,
                'action' => 'Run Auto Backup Now',
                'model_name' => AppSetting::class,
                'model_id' => 0,
                'before' => null,
                'after' => [
                    'exit_code' => $exitCode,
                    'output' => $output,
                ],
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'message' => $exitCode === 0 ? 'Auto backup completed.' : 'Auto backup completed with issues.',
                'success' => $exitCode === 0,
                'exit_code' => $exitCode,
                'output' => $output,
            ], $exitCode === 0 ? 200 : 207);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => 'Unable to run auto backup.',
                'error' => $exception->getMessage(),
            ], 422);
        }
    }

    public function downloadBackupFile(Request $request)
    {
        if (!$this->isSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
        ]);

        try {
            $path = $this->resolveBackupFilePath((string) $validated['filename']);

            if (!Storage::disk('local')->exists($path)) {
                return response()->json([
                    'message' => 'Backup file not found.',
                ], 404);
            }

            return Storage::disk('local')->download($path, basename($path));
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => 'Unable to download backup file.',
                'error' => $exception->getMessage(),
            ], 422);
        }
    }

    public function deleteBackupFile(Request $request)
    {
        if (!$this->isSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
        ]);

        try {
            $path = $this->resolveBackupFilePath((string) $validated['filename']);

            if (!Storage::disk('local')->exists($path)) {
                return response()->json([
                    'message' => 'Backup file not found.',
                ], 404);
            }

            Storage::disk('local')->delete($path);

            ActivityLog::create([
                'user_id' => $request->user()?->id,
                'action' => 'Deleted Backup File',
                'model_name' => AppSetting::class,
                'model_id' => 0,
                'before' => [
                    'filename' => (string) $validated['filename'],
                ],
                'after' => null,
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Backup file deleted.',
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => 'Unable to delete backup file.',
                'error' => $exception->getMessage(),
            ], 422);
        }
    }

    public function restoreBackupFile(Request $request, DatabaseBackupService $backupService)
    {
        if (!$this->isSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'passphrase' => ['required', 'string', 'min:8', 'max:128'],
        ]);

        try {
            $filename = (string) $validated['filename'];
            $path = $this->resolveBackupFilePath($filename);

            if (!Storage::disk('local')->exists($path)) {
                return response()->json([
                    'message' => 'Backup file not found.',
                ], 404);
            }

            $content = Storage::disk('local')->get($path);
            $snapshot = $backupService->decryptSnapshot($content, (string) $validated['passphrase']);
            $result = $backupService->restoreSnapshot($snapshot);

            ActivityLog::create([
                'user_id' => $request->user()?->id,
                'action' => 'Restored Backup File',
                'model_name' => AppSetting::class,
                'model_id' => 0,
                'before' => [
                    'filename' => $filename,
                ],
                'after' => [
                    'restored_tables' => $result['restored_tables'],
                    'missing_tables' => $result['missing_tables'],
                ],
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Backup restored successfully.',
                'restored_tables' => $result['restored_tables'],
                'missing_tables' => $result['missing_tables'],
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => 'Unable to restore backup file.',
                'error' => $exception->getMessage(),
            ], 422);
        }
    }
}
