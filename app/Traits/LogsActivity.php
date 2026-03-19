<?php

namespace App\Traits;

use App\Observers\GeneralObserver;

trait LogsActivity {
    public static function bootLogsActivity() {
        static::observe(GeneralObserver::class);
    }
}