<?php

use App\Models\AppSetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sync:counts')->daily();
Schedule::command('attendance:auto-sync')->everySecond()->withoutOverlapping()->runInBackground();

// ── Auto database backup (configured in app_settings) ─────────────────────
try {
    if (Schema::hasTable('app_settings')) {
        $settings = AppSetting::query()
            ->whereIn('setting_key', [
                'backup_auto_enabled',
                'backup_auto_schedule',
                'backup_auto_time',
            ])
            ->pluck('setting_value', 'setting_key');

        if (filter_var($settings['backup_auto_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $backupSchedule = (string) ($settings['backup_auto_schedule'] ?? 'daily');
            $backupTime = (string) ($settings['backup_auto_time'] ?? '02:00');

            $event = Schedule::command('db:auto-backup')->withoutOverlapping();

            match ($backupSchedule) {
                'hourly' => $event->hourly(),
                'everyTwelveHours' => $event->everyTwelveHours(),
                'weekly' => $event->weekly()->at($backupTime),
                default => $event->daily()->at($backupTime),
            };
        }
    }
} catch (\Throwable) {
    // keep scheduler boot resilient during early setup or migration states
}