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
    private const ATTENDANCE_DAEMON_TASK_NAME = 'attendance-auto-sync';

    private const DEFAULT_SETTINGS = [
        'company_school_name' => 'Biometric System',
        'company_school_logo' => '',
        'company_school_logo_print_enabled' => false,
        'biometric_dtr_signatory_name' => '',
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
            'optimize:clear',
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

    private function resolveWindowsPhpBinary(): array
    {
        $candidates = [];

        $fromWhere = $this->runShellCommand('where php');
        if (($fromWhere['success'] ?? false) && filled($fromWhere['output'] ?? null)) {
            $lines = preg_split('/\r\n|\r|\n/', (string) $fromWhere['output']) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $candidates[] = $line;
                }
            }
        }

        $envNativePath = env('NATIVEPHP_PHP_BINARY_PATH');
        if (filled($envNativePath)) {
            $nativePath = (string) $envNativePath;
            $candidates[] = $nativePath;
            $candidates[] = rtrim($nativePath, '\\/') . DIRECTORY_SEPARATOR . 'php.exe';
            $candidates[] = rtrim($nativePath, '\\/') . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'php.exe';
        }

        $candidates[] = PHP_BINARY;
        $candidates[] = PHP_BINDIR . DIRECTORY_SEPARATOR . 'php.exe';
        $candidates[] = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'php.exe';
        $candidates[] = base_path('vendor/nativephp/electron/resources/js/resources/php/php.exe');
        $candidates[] = base_path('vendor/nativephp/php-bin/bin/php.exe');
        $candidates[] = 'C:\\php\\php.exe';
        $candidates[] = 'C:\\xampp\\php\\php.exe';
        $candidates[] = 'C:\\laragon\\bin\\php\\php.exe';
        $candidates[] = 'C:\\Program Files\\PHP\\php.exe';

        $normalized = [];
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate, " \t\n\r\0\x0B\"");
            if ($candidate === '') {
                continue;
            }

            $candidate = str_replace('/', DIRECTORY_SEPARATOR, $candidate);
            $key = strtolower($candidate);

            if (isset($normalized[$key])) {
                continue;
            }

            $normalized[$key] = $candidate;
        }

        foreach ($normalized as $candidate) {
            if (!is_file($candidate)) {
                continue;
            }

            $filename = strtolower((string) pathinfo($candidate, PATHINFO_BASENAME));
            if (in_array($filename, ['php.exe', 'php-cgi.exe'], true)) {
                return [
                    'path' => $candidate,
                    'source' => ($fromWhere['success'] ?? false) ? 'PATH/where or fallback candidates' : 'fallback candidates',
                    'probe' => $fromWhere,
                ];
            }
        }

        return [
            'path' => null,
            'source' => 'not found',
            'probe' => $fromWhere,
            'candidates' => array_values($normalized),
        ];
    }

    private function resolveWindowsSchtasksBinary(): array
    {
        $candidates = [];
        $fromWhere = $this->runShellCommand('where schtasks');

        if (($fromWhere['success'] ?? false) && filled($fromWhere['output'] ?? null)) {
            $lines = preg_split('/\r\n|\r|\n/', (string) $fromWhere['output']) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $candidates[] = $line;
                }
            }
        }

        $windowsDir = getenv('WINDIR') ?: 'C:\\Windows';
        $candidates[] = rtrim((string) $windowsDir, '\\/') . DIRECTORY_SEPARATOR . 'System32' . DIRECTORY_SEPARATOR . 'schtasks.exe';
        $candidates[] = rtrim((string) $windowsDir, '\\/') . DIRECTORY_SEPARATOR . 'Sysnative' . DIRECTORY_SEPARATOR . 'schtasks.exe';
        $candidates[] = 'C:\\Windows\\System32\\schtasks.exe';
        $candidates[] = 'C:\\Windows\\Sysnative\\schtasks.exe';

        $normalized = [];
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate, " \t\n\r\0\x0B\"");
            if ($candidate === '') {
                continue;
            }

            $candidate = str_replace('/', DIRECTORY_SEPARATOR, $candidate);
            $key = strtolower($candidate);

            if (isset($normalized[$key])) {
                continue;
            }

            $normalized[$key] = $candidate;
        }

        foreach ($normalized as $candidate) {
            if (is_file($candidate)) {
                return [
                    'path' => $candidate,
                    'probe' => $fromWhere,
                ];
            }
        }

        return [
            'path' => null,
            'probe' => $fromWhere,
            'candidates' => array_values($normalized),
        ];
    }

    private function resolveWindowsStartupDirectory(): ?string
    {
        $appData = getenv('APPDATA');
        if (is_string($appData) && trim($appData) !== '') {
            return rtrim($appData, '\\/') . DIRECTORY_SEPARATOR . 'Microsoft' . DIRECTORY_SEPARATOR . 'Windows' . DIRECTORY_SEPARATOR . 'Start Menu' . DIRECTORY_SEPARATOR . 'Programs' . DIRECTORY_SEPARATOR . 'Startup';
        }

        $userProfile = getenv('USERPROFILE');
        if (is_string($userProfile) && trim($userProfile) !== '') {
            return rtrim($userProfile, '\\/') . DIRECTORY_SEPARATOR . 'AppData' . DIRECTORY_SEPARATOR . 'Roaming' . DIRECTORY_SEPARATOR . 'Microsoft' . DIRECTORY_SEPARATOR . 'Windows' . DIRECTORY_SEPARATOR . 'Start Menu' . DIRECTORY_SEPARATOR . 'Programs' . DIRECTORY_SEPARATOR . 'Startup';
        }

        return null;
    }

    public function attendanceDaemonStatus(Request $request)
    {
        if (!$this->isSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $isWindows = DIRECTORY_SEPARATOR === '\\';
        $isLinux = DIRECTORY_SEPARATOR === '/';
        $configPath = '/etc/supervisor/conf.d/attendance-auto-sync.conf';

        if ($isWindows) {
            $taskName = self::ATTENDANCE_DAEMON_TASK_NAME;
            $schtasksResolution = $this->resolveWindowsSchtasksBinary();
            $schtasksBinary = $schtasksResolution['path'] ?? null;
            $schedulerInstalled = is_string($schtasksBinary) && $schtasksBinary !== '';
            $startupDir = $this->resolveWindowsStartupDirectory();
            $startupLauncherPath = $startupDir
                ? rtrim($startupDir, '\\/') . DIRECTORY_SEPARATOR . self::ATTENDANCE_DAEMON_TASK_NAME . '-startup.bat'
                : null;
            $startupLauncherExists = is_string($startupLauncherPath) && is_file($startupLauncherPath);

            if (!$schedulerInstalled) {
                return response()->json([
                    'available' => true,
                    'os' => php_uname('s'),
                    'config_path' => $startupLauncherExists
                        ? ('Startup Folder: ' . $startupLauncherPath)
                        : ('Task Scheduler: ' . $taskName),
                    'config_exists' => $startupLauncherExists,
                    'service_running' => false,
                    'status_output' => $schtasksResolution['probe']['output'] ?? '',
                    'status_exit_code' => $schtasksResolution['probe']['exit_code'] ?? null,
                    'scheduler_installed' => false,
                    'scheduler_candidates' => $schtasksResolution['candidates'] ?? [],
                    'startup_launcher_exists' => $startupLauncherExists,
                    'startup_launcher_path' => $startupLauncherPath,
                    'php_binary' => PHP_BINARY,
                    'app_path' => base_path(),
                ]);
            }

            $query = $this->runShellCommand('"' . $schtasksBinary . '" /Query /TN "' . $taskName . '" /V /FO LIST');
            $taskExists = $query['success'] ?? false;
            $statusOutput = (string) ($query['output'] ?? '');
            $running = $taskExists && preg_match('/^Status:\s*Running\b/im', $statusOutput) === 1;
            $configExists = $taskExists || $startupLauncherExists;
            $configPath = $taskExists
                ? ('Task Scheduler: ' . $taskName)
                : ($startupLauncherPath ? ('Startup Folder: ' . $startupLauncherPath) : ('Task Scheduler: ' . $taskName));

            return response()->json([
                'available' => true,
                'os' => php_uname('s'),
                'config_path' => $configPath,
                'config_exists' => $configExists,
                'service_running' => $running,
                'status_output' => $statusOutput,
                'status_exit_code' => $query['exit_code'] ?? null,
                'scheduler_installed' => $schedulerInstalled,
                'startup_launcher_exists' => $startupLauncherExists,
                'startup_launcher_path' => $startupLauncherPath,
                'php_binary' => PHP_BINARY,
                'app_path' => base_path(),
            ]);
        }

        if (!$isLinux) {
            return response()->json([
                'available' => false,
                'message' => 'Attendance daemon status is only available on Linux and Windows servers.',
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

        $isWindows = DIRECTORY_SEPARATOR === '\\';
        $isLinux = DIRECTORY_SEPARATOR === '/';
        if (!$isLinux && !$isWindows) {
            return response()->json([
                'message' => 'Installation is only supported on Linux and Windows servers.',
            ], 422);
        }

        $validated = $request->validate([
            'sleep' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $sleep = (int) ($validated['sleep'] ?? 1);

        if ($isWindows) {
            $taskName = self::ATTENDANCE_DAEMON_TASK_NAME;
            $steps = [];

            $schtasksResolution = $this->resolveWindowsSchtasksBinary();
            $schtasksBinary = $schtasksResolution['path'] ?? null;
            if (!is_string($schtasksBinary) || trim($schtasksBinary) === '') {
                return response()->json([
                    'message' => 'Unable to resolve schtasks.exe for Windows scheduled task operations.',
                    'output' => $schtasksResolution['probe']['output'] ?? '',
                    'candidates' => $schtasksResolution['candidates'] ?? [],
                ], 422);
            }

            $steps[] = [
                'command' => 'resolve schtasks binary',
                'success' => true,
                'exit_code' => 0,
                'output' => $schtasksBinary,
            ];

            $phpResolution = $this->resolveWindowsPhpBinary();
            $resolvedPhpBinary = $phpResolution['path'] ?? null;

            if (!is_string($resolvedPhpBinary) || trim($resolvedPhpBinary) === '') {
                return response()->json([
                    'message' => 'Unable to resolve a valid CLI php binary for Windows scheduled task.',
                    'output' => $phpResolution['probe']['output'] ?? '',
                    'candidates' => $phpResolution['candidates'] ?? [],
                    'php_binary_runtime' => PHP_BINARY,
                    'php_bindir' => PHP_BINDIR,
                ], 422);
            }

            $resolvedPhpBinary = trim($resolvedPhpBinary);
            $steps[] = [
                'command' => 'resolve php binary',
                'success' => true,
                'exit_code' => 0,
                'output' => $resolvedPhpBinary,
            ];

            $logPath = str_replace('/', DIRECTORY_SEPARATOR, storage_path('logs/attendance-auto-sync.log'));
            $errorLogPath = str_replace('/', DIRECTORY_SEPARATOR, storage_path('logs/attendance-auto-sync-error.log'));
            $artisanPath = str_replace('/', DIRECTORY_SEPARATOR, base_path('artisan'));
            $workingPath = str_replace('/', DIRECTORY_SEPARATOR, base_path());

            $batPath = str_replace('/', DIRECTORY_SEPARATOR, storage_path('app/private/attendance-auto-sync-daemon.bat'));
            @mkdir(dirname($batPath), 0755, true);

            $batContent = implode(PHP_EOL, [
                '@echo off',
                'cd /d "' . $workingPath . '"',
                '"' . $resolvedPhpBinary . '" "' . $artisanPath . '" attendance:auto-sync:daemon --sleep=' . $sleep . ' >> "' . $logPath . '" 2>> "' . $errorLogPath . '"',
                '',
            ]);

            file_put_contents($batPath, $batContent);
            $steps[] = [
                'command' => 'write daemon bat',
                'success' => true,
                'exit_code' => 0,
                'output' => $batPath,
            ];

            $createAttempts = [
                [
                    'label' => 'schtasks /Create (SYSTEM ONSTART)',
                    'command' => '"' . $schtasksBinary . '" /Create /TN "' . $taskName . '" /TR "\\"' . $batPath . '\\"" /SC ONSTART /RU SYSTEM /RL HIGHEST /F',
                ],
                [
                    'label' => 'schtasks /Create (current user ONLOGON)',
                    'command' => '"' . $schtasksBinary . '" /Create /TN "' . $taskName . '" /TR "\\"' . $batPath . '\\"" /SC ONLOGON /RL LIMITED /F',
                ],
            ];

            $create = null;
            foreach ($createAttempts as $attempt) {
                $attemptResult = $this->runShellCommand($attempt['command']);
                $steps[] = array_merge(['command' => $attempt['label']], $attemptResult);

                if ($attemptResult['success'] ?? false) {
                    $create = $attemptResult;
                    break;
                }
            }

            if ($create === null) {
                $create = [
                    'success' => false,
                    'exit_code' => 1,
                    'output' => 'Unable to create Windows scheduled task with either SYSTEM or current-user mode.',
                ];
            }

            $installMode = 'task_scheduler';

            if (!($create['success'] ?? false)) {
                $startupDir = $this->resolveWindowsStartupDirectory();
                if (!$startupDir) {
                    return response()->json([
                        'message' => 'Unable to create Windows scheduled task and APPDATA startup folder was not found.',
                        'step' => 'startup_fallback',
                        'commands' => $steps,
                    ], 422);
                }

                @mkdir($startupDir, 0755, true);
                if (!is_dir($startupDir) || !is_writable($startupDir)) {
                    return response()->json([
                        'message' => 'Unable to create Windows scheduled task, and startup folder is not writable.',
                        'step' => 'startup_fallback',
                        'startup_dir' => $startupDir,
                        'commands' => $steps,
                    ], 422);
                }

                $startupLauncherPath = rtrim($startupDir, '\\/') . DIRECTORY_SEPARATOR . self::ATTENDANCE_DAEMON_TASK_NAME . '-startup.bat';
                $launcherContent = implode(PHP_EOL, [
                    '@echo off',
                    'setlocal',
                    'set LOG_DIR=' . str_replace('/', DIRECTORY_SEPARATOR, dirname($logPath)),
                    'if not exist "%LOG_DIR%" mkdir "%LOG_DIR%"',
                    'cd /d "' . $workingPath . '"',
                    'start "Attendance Auto Sync" /min cmd /c ""' . $resolvedPhpBinary . '" "' . $artisanPath . '" attendance:auto-sync:daemon --sleep=' . $sleep . ' >> "' . $logPath . '" 2>> "' . $errorLogPath . '""',
                    '',
                ]);

                file_put_contents($startupLauncherPath, $launcherContent);
                $steps[] = [
                    'command' => 'write startup launcher',
                    'success' => is_file($startupLauncherPath),
                    'exit_code' => is_file($startupLauncherPath) ? 0 : 1,
                    'output' => $startupLauncherPath,
                ];

                if (!is_file($startupLauncherPath)) {
                    return response()->json([
                        'message' => 'Unable to create Windows scheduled task and failed to write startup launcher.',
                        'step' => 'startup_fallback',
                        'commands' => $steps,
                    ], 422);
                }

                // Kick off one daemon instance now so user does not need to log off/on first.
                $startNow = $this->runShellCommand('cmd /c "start \"\" /min cmd /c \"\"' . $batPath . '\"\""');
                $steps[] = array_merge(['command' => 'start daemon now (startup fallback)'], $startNow);
                $installMode = 'startup_folder';
            }

            $runTask = [
                'success' => true,
                'exit_code' => 0,
                'output' => 'Skipped: startup folder mode',
            ];
            if ($installMode === 'task_scheduler') {
                $runTask = $this->runShellCommand('"' . $schtasksBinary . '" /Run /TN "' . $taskName . '"');
            }
            $steps[] = array_merge(['command' => 'schtasks /Run'], $runTask);

            $status = [
                'success' => true,
                'exit_code' => 0,
                'output' => 'Startup folder mode configured',
            ];
            if ($installMode === 'task_scheduler') {
                $status = $this->runShellCommand('"' . $schtasksBinary . '" /Query /TN "' . $taskName . '" /V /FO LIST');
            }
            $steps[] = array_merge(['command' => 'schtasks /Query'], $status);

            ActivityLog::create([
                'user_id' => $request->user()?->id,
                'action' => 'Installed Attendance Auto Sync Daemon',
                'model_name' => AppSetting::class,
                'model_id' => 0,
                'before' => null,
                'after' => [
                    'config_path' => $installMode === 'task_scheduler'
                        ? ('Task Scheduler: ' . $taskName)
                        : ('Startup Folder: ' . (isset($startupLauncherPath) ? $startupLauncherPath : 'unknown')),
                    'sleep' => $sleep,
                    'user' => 'SYSTEM',
                    'php_binary' => $resolvedPhpBinary,
                    'mode' => $installMode,
                ],
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'message' => $installMode === 'task_scheduler'
                    ? 'Attendance auto-sync daemon installed and started via Windows Task Scheduler.'
                    : 'Attendance auto-sync daemon installed via Windows Startup folder fallback and started.',
                'config_path' => $installMode === 'task_scheduler'
                    ? ('Task Scheduler: ' . $taskName)
                    : ('Startup Folder: ' . (isset($startupLauncherPath) ? $startupLauncherPath : 'unknown')),
                'php_binary' => $resolvedPhpBinary,
                'mode' => $installMode,
                'commands' => $steps,
            ]);
        }

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
        @set_time_limit(0);
        if (!$this->isSuperAdmin($request)) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'passphrase' => ['required', 'string', 'min:8', 'max:128'],
        ]);

        try {
            $timestamp = now()->utc()->format('Ymd_His');
            $filename = "db-backup-{$timestamp}.bkp";
            $path = "backups/{$filename}";
            $result = $backupService->createEncryptedServerBackup(
                Storage::disk('local')->path($path),
                (string) $validated['passphrase']
            );

            return response()->json([
                'message' => 'Encrypted database backup created.',
                'filename' => $filename,
                'path' => $path,
                'size_bytes' => $result['size_bytes'],
                'format' => $result['format'],
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
            $result = $backupService->restoreEncryptedBackupFile(
                $validated['backup_file']->getRealPath(),
                (string) $validated['passphrase']
            );

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

            $result = $backupService->restoreEncryptedBackupFile(
                Storage::disk('local')->path($path),
                (string) $validated['passphrase']
            );

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
