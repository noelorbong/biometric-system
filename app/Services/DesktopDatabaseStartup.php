<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class DesktopDatabaseStartup
{
    public function migrate(): void
    {
        if (! config('nativephp-internal.running') || config('database.default') !== 'nativephp') {
            throw new RuntimeException('Desktop startup requires the NativePHP database connection.');
        }

        $status = Artisan::call('migrate', [
            '--database' => 'nativephp',
            '--force' => true,
            '--no-interaction' => true,
        ]);

        if ($status !== 0) {
            throw new RuntimeException('Desktop database migration failed: '.Artisan::output());
        }
    }
}
