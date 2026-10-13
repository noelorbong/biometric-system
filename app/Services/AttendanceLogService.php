<?php

namespace App\Services;

use App\Models\BiometricLogOverride;
use App\Models\Checkinout;
use Carbon\CarbonImmutable;

class AttendanceLogService
{
    public function forUsers(array $ids, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $logQuery = Checkinout::whereIn('USERID', $ids)->where('CHECKTIME', '>=', $start)
            ->where('CHECKTIME', '<', $end);
        $logs = (clone $logQuery)->get(['id', 'USERID', 'CHECKTIME', 'CHECKTYPE']);
        // Include overrides moved outside this range so their original punches stay suppressed.
        $overrides = BiometricLogOverride::whereIn('user_id', $ids)
            ->where(function ($query) use ($start, $end, $logQuery) {
                $query->where(function ($query) use ($start, $end) {
                    $query->where('new_checktime', '>=', $start)->where('new_checktime', '<', $end);
                })->orWhereIn('checkinout_id', (clone $logQuery)->select('id'));
            })->orderByDesc('id')->get();
        $replaced = array_fill_keys($overrides->where('action_type', 'override')->pluck('checkinout_id')->filter()->all(), true);
        $punches = [];
        foreach ($logs as $log) {
            if (!isset($replaced[$log->id])) {
                $punches[$log->USERID][] = ['time' => $log->CHECKTIME->format('Y-m-d H:i:s'), 'type' => $log->CHECKTYPE];
            }
        }
        $seen = [];
        foreach ($overrides as $override) {
            if ($override->checkinout_id && isset($seen[$override->checkinout_id])) {
                continue;
            }
            if ($override->checkinout_id) {
                $seen[$override->checkinout_id] = true;
            }
            if ($override->new_checktime && $override->new_checktime >= $start && $override->new_checktime < $end) {
                $punches[$override->user_id][] = ['time' => $override->new_checktime->format('Y-m-d H:i:s'),
                    'type' => $override->new_checktype, 'corrected' => true];
            }
        }
        return $punches;
    }
}
