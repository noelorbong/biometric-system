<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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

        $settings = AppSetting::query()
            ->orderBy('setting_key')
            ->pluck('setting_value', 'setting_key');

        $resolved = [
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
                    'user_last_modify' => $request->user()?->id,
                ]);
                continue;
            }

            AppSetting::create([
                'setting_key' => $key,
                'setting_value' => $value,
                'user_add' => $request->user()?->id,
                'user_last_modify' => $request->user()?->id,
            ]);
        }

        $settings = AppSetting::query()
            ->orderBy('setting_key')
            ->pluck('setting_value', 'setting_key');

        $resolved = [
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
}
