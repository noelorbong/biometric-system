<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class AutoBackupDatabase extends Command
{
    protected $signature = 'db:auto-backup';

    protected $description = 'Create an encrypted database backup automatically based on BACKUP_* env configuration';

    public function handle(DatabaseBackupService $backupService): int
    {
        if (!Schema::hasTable('app_settings')) {
            $this->line('Auto backup skipped because app_settings table is not available.');
            return self::SUCCESS;
        }

        $settings = AppSetting::query()
            ->whereIn('setting_key', [
                'backup_auto_enabled',
                'backup_auto_retain_days',
                'backup_auto_passphrase_encrypted',
            ])
            ->pluck('setting_value', 'setting_key');

        if (!filter_var($settings['backup_auto_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $this->line('Auto backup is disabled in app settings.');
            return self::SUCCESS;
        }

        $encryptedPassphrase = (string) ($settings['backup_auto_passphrase_encrypted'] ?? '');
        $passphrase = '';

        if ($encryptedPassphrase !== '') {
            try {
                $passphrase = Crypt::decryptString($encryptedPassphrase);
            } catch (\Throwable) {
                $passphrase = '';
            }
        }

        if (strlen(trim($passphrase)) < 8) {
            $this->error('Auto backup passphrase is missing or invalid in app settings. Backup aborted.');
            return self::FAILURE;
        }

        try {
            $this->line('Building database snapshot...');
            $snapshot = $backupService->buildSnapshot();

            $this->line('Encrypting snapshot...');
            $encrypted = $backupService->encryptSnapshot($snapshot, $passphrase);

            $timestamp = now()->utc()->format('Ymd_His');
            $filename = "db-auto-{$timestamp}.bkp";
            $path = "backups/{$filename}";

            Storage::disk('local')->put($path, $encrypted);

            $sizeKb = round(strlen($encrypted) / 1024, 1);
            $this->info("Backup stored: {$path} ({$sizeKb} KB)");

            $this->pruneOldBackups((int) ($settings['backup_auto_retain_days'] ?? 7));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('Backup failed: ' . $exception->getMessage());
            return self::FAILURE;
        }
    }

    private function pruneOldBackups(int $retainDays): void
    {
        $retainDays = max(1, $retainDays);
        $files      = Storage::disk('local')->files('backups');
        $cutoff     = now()->utc()->subDays($retainDays)->timestamp;
        $deleted    = 0;

        foreach ($files as $file) {
            if (!str_ends_with($file, '.bkp')) {
                continue;
            }

            try {
                $lastModified = Storage::disk('local')->lastModified($file);
                if ($lastModified < $cutoff) {
                    Storage::disk('local')->delete($file);
                    $deleted++;
                }
            } catch (\Throwable) {
                // skip unreadable files
            }
        }

        if ($deleted > 0) {
            $this->line("Pruned {$deleted} backup(s) older than {$retainDays} days.");
        }
    }
}
