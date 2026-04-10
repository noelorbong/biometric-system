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
            'machine_auto_sync_status_timer_enabled' => ['required', 'boolean'],
            'machine_auto_sync_status_timer_ms' => ['nullable', 'integer', 'min:250', 'max:300000'],
            'machine_refresh_timer_enabled' => ['required', 'boolean'],
            'machine_refresh_timer_ms' => ['nullable', 'integer', 'min:250', 'max:300000'],
            'machine_web_auto_fallback_timer_enabled' => ['required', 'boolean'],
            'machine_web_auto_fallback_timer_ms' => ['nullable', 'integer', 'min:250', 'max:300000'],
        ]);

        $pairs = [
            'company_school_name' => $validated['company_school_name'],
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
