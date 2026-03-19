<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class AutoSyncAttendanceDaemon extends Command
{
    protected $signature = 'attendance:auto-sync:daemon {--sleep=1 : Delay between cycles in seconds}';

    protected $description = 'Run attendance auto-sync as a long-running daemon loop';

    public function handle(): int
    {
        $sleep = max(1, (int) $this->option('sleep'));
        $heartbeatKey = 'attendance:auto-sync:daemon:heartbeat';

        $this->info('Starting attendance auto-sync daemon. Press CTRL+C to stop.');
        $this->line('Loop delay: ' . $sleep . ' second(s)');

        Cache::put($heartbeatKey, [
            'timestamp' => Carbon::now()->toIso8601String(),
            'sleep' => $sleep,
            'pid' => getmypid(),
        ], now()->addMinutes(5));

        while (true) {
            try {
                Artisan::call('attendance:auto-sync');
                $output = trim((string) Artisan::output());

                Cache::put($heartbeatKey, [
                    'timestamp' => Carbon::now()->toIso8601String(),
                    'sleep' => $sleep,
                    'pid' => getmypid(),
                ], now()->addMinutes(5));

                if ($output !== '') {
                    $this->line('[' . now()->toDateTimeString() . '] ' . $output);
                }
            } catch (\Throwable $e) {
                $this->error('[' . now()->toDateTimeString() . '] Daemon cycle failed: ' . $e->getMessage());
            }

            sleep($sleep);
        }
    }
}
