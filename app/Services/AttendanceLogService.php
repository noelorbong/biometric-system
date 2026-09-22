<?php

namespace App\Services;

use App\Models\BiometricLogOverride;
use App\Models\Checkinout;
use Carbon\CarbonImmutable;

class AttendanceLogService
{
    public function forUsers(array $ids, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $logs = Checkinout::whereIn('USERID', $ids)->where('CHECKTIME', '>=', $start)
            ->where('CHECKTIME', '<', $end)->get(['id', 'USERID', 'CHECKTIME', 'CHECKTYPE']);
        // Include overrides moved outside this range so their original punches stay suppressed.
        $overrides = BiometricLogOverride::whereIn('user_id', $ids)
            ->where(function ($query) use ($start, $end, $logs) {
                $query->where(function ($query) use ($start, $end) {
                    $query->where('new_checktime', '>=', $start)->where('new_checktime', '<', $end);
                })->orWhereIn('checkinout_id', $logs->modelKeys());
            })->orderByDesc('id')->get();
        $replaced = $overrides->where('action_type', 'override')->pluck('checkinout_id')->filter()->all();
        $punches = [];
        foreach ($logs as $log) {
            if (!in_array($log->id, $replaced)) {
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
