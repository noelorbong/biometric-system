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
        $this->line('[db:auto-backup] Starting...');

        // ── 1. Check migrations ────────────────────────────────────────────
        if (!Schema::hasTable('app_settings')) {
            $this->warn('[db:auto-backup] Skipped: app_settings table does not exist yet. Run migrations first.');
            return self::SUCCESS;
        }

        // ── 2. Load settings ───────────────────────────────────────────────
        $settings = AppSetting::query()
            ->whereIn('setting_key', [
                'backup_auto_enabled',
                'backup_auto_retain_days',
                'backup_auto_passphrase_encrypted',
            ])
            ->pluck('setting_value', 'setting_key');

        if (!filter_var($settings['backup_auto_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $this->line('[db:auto-backup] Skipped: auto backup is disabled in app settings.');
            return self::SUCCESS;
        }

        // ── 3. Decrypt passphrase ──────────────────────────────────────────
        $encryptedPassphrase = (string) ($settings['backup_auto_passphrase_encrypted'] ?? '');
        $passphrase = '';

        if ($encryptedPassphrase === '') {
            $this->error('[db:auto-backup] No passphrase stored in app settings. Set one from Database Settings.');
            return self::FAILURE;
        }

        try {
            $passphrase = Crypt::decryptString($encryptedPassphrase);
        } catch (\Throwable $e) {
            $this->error('[db:auto-backup] Passphrase decryption failed. APP_KEY on this server may differ from where the passphrase was stored. Error: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (strlen(trim($passphrase)) < 8) {
            $this->error('[db:auto-backup] Decrypted passphrase is too short (< 8 chars). Re-save the passphrase from Database Settings.');
            return self::FAILURE;
        }

        // ── 4. Ensure backups directory exists ─────────────────────────────
        $backupDir = Storage::disk('local')->path('backups');
        if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
            $this->error("[db:auto-backup] Cannot create backups directory: {$backupDir}");
            return self::FAILURE;
        }

        if (!is_writable($backupDir)) {
            $this->error("[db:auto-backup] Backups directory is not writable: {$backupDir}");
            $this->line('Run: chmod -R 775 ' . Storage::disk('local')->path(''));
            return self::FAILURE;
        }

        // ── 5. Build + encrypt + store ─────────────────────────────────────
        try {
            $this->line('[db:auto-backup] Building database snapshot...');
            $snapshot = $backupService->buildSnapshot();
            $tableCount = count($snapshot['tables'] ?? []);
            $this->line("[db:auto-backup] Snapshot built ({$tableCount} tables).");

            $this->line('[db:auto-backup] Encrypting snapshot...');
            $encrypted = $backupService->encryptSnapshot($snapshot, $passphrase);

            $timestamp = now()->utc()->format('Ymd_His');
            $filename  = "db-auto-{$timestamp}.bkp";
            $storagePath = "backups/{$filename}";

            Storage::disk('local')->put($storagePath, $encrypted);

            $fullPath = Storage::disk('local')->path($storagePath);
            $sizeKb   = round(strlen($encrypted) / 1024, 1);
            $this->info("[db:auto-backup] Backup stored: {$fullPath} ({$sizeKb} KB)");

            $this->pruneOldBackups((int) ($settings['backup_auto_retain_days'] ?? 7));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('[db:auto-backup] Backup failed: ' . $exception->getMessage());
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
