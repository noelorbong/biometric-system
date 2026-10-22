<?php

namespace App\Console;

use Illuminate\Database\Console\Migrations\MigrateCommand;
use Illuminate\Support\Facades\File;
use RuntimeException;

class DesktopMigrateCommand extends MigrateCommand
{
    public function handle()
    {
        if (! config('nativephp-internal.running')) {
            return parent::handle();
        }

        // File locking works even before cache and migrations tables exist.
        File::ensureDirectoryExists(storage_path('app/private'));
        $lock = fopen(storage_path('app/private/migrations.lock'), 'c');
        if ($lock === false) {
            throw new RuntimeException('Cannot open the desktop migration lock.');
        }

        try {
            set_time_limit(0);
            $deadline = microtime(true) + 60;
            while (! flock($lock, LOCK_EX | LOCK_NB)) {
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('Another database upgrade is still running. Please retry shortly.');
                }
                usleep(100000);
            }

            return parent::handle();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
