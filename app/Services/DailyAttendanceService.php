<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class DailyAttendanceService
{
    /** Match punches to schedule periods, never to their position in the log list. */
    public function calculate(string $date, ?array $shift, array $punches, array $holidays, CarbonImmutable $now): array
    {
        $day = CarbonImmutable::parse($date, $now->timezone)->startOfDay();
        $restDay = ($shift['_is_working_day'] ?? null) === false;
        $result = ['periods' => [], 'late_minutes' => 0, 'undertime_minutes' => 0,
            'rest_day' => $restDay, 'holiday_credit_minutes' => 0,
            'schedule_name' => $shift['name'] ?? null, 'schedule_source' => $shift['_source'] ?? 'Office shift',
            'missing_count' => 0, 'pending' => false, 'first_in' => null,
            'status' => 'No schedule', 'punches' => []];
        usort($punches, fn ($a, $b) => strcmp($a['time'], $b['time']));
        $result['punches'] = array_values(array_filter($punches, fn ($p) => substr($p['time'], 0, 10) === $date));
        if (!$shift || !empty($shift['is_flexible']) || empty($shift['schedules'])) {
            $result['status'] = !empty($shift['is_flexible']) ? 'Flexible shift' : 'No schedule';
            return $result;
        }

        $schedules = $shift['schedules'];
        usort($schedules, fn ($a, $b) => $a['sequence'] <=> $b['sequence']);
        // Adjacent days keep overnight punches and early arrivals in their own shift.
        $timeline = [];
        foreach ([-1, 0, 1] as $offset) {
            foreach ($schedules as $index => $schedule) {
                $start = $day->addDays($offset)->setTimeFromTimeString($schedule['time_in']);
                $end = $day->addDays($offset)->setTimeFromTimeString($schedule['time_out']);
                if (!empty($schedule['is_next_day']) || $end <= $start) {
                    $end = $end->addDay();
                }
                $timeline[] = ['start' => $start, 'end' => $end, 'index' => $index,
                    'current' => $offset === 0, 'in' => null, 'out' => null];
            }
        }
        usort($timeline, fn ($a, $b) => $a['start'] <=> $b['start']);

        foreach ($punches as $punch) {
            $time = CarbonImmutable::parse($punch['time'], $now->timezone);
            if ($time > $now) {
                continue;
            }
            $type = strtoupper($punch['type']);
            $target = null;
            $distance = PHP_INT_MAX;
            if (!empty($shift['grace_enabled'])) {
                foreach ($timeline as $i => $period) {
                    foreach (['I' => 'start', 'O' => 'end'] as $candidateType => $boundary) {
                        $seconds = $time->timestamp - $period[$boundary]->timestamp;
                        if ($seconds >= -(int) ($shift['grace_before_minutes'] ?? 0) * 60
                            && $seconds <= (int) ($shift['grace_after_minutes'] ?? 0) * 60
                            && abs($seconds) < $distance) {
                            $target = $i;
                            $type = $candidateType;
                            $distance = abs($seconds);
                        }
                    }
                }
            }
            if (!in_array($type, ['I', 'O'], true)) {
                continue;
            }
            if ($target === null) {
                foreach ($timeline as $i => $period) {
                    $previousEnd = $timeline[$i - 1]['end'] ?? $period['start']->startOfDay();
                    $nextStart = $timeline[$i + 1]['start'] ?? $period['end']->addDay()->startOfDay();
                    if (($type === 'I' && $time >= $previousEnd && $time < $period['end'])
                        || ($type === 'O' && $time > $period['start'] && $time <= $nextStart)) {
                        $target = $i;
                        break;
                    }
                }
            }
            if ($target === null || !$timeline[$target]['current']) {
                continue;
            }
            $key = $type === 'I' ? 'in' : 'out';
            $existing = $timeline[$target][$key];
            if (!$existing || ($type === 'I' ? $time < $existing : $time > $existing)) {
                $timeline[$target][$key] = $time;
            }
        }

        $exemptCount = 0;
        foreach ($timeline as $period) {
            if (!$period['current']) {
                continue;
            }
            $exempt = false;
            foreach ($holidays as $holiday) {
                if (!empty($holiday['is_working_day'])) {
                    continue;
                }
                $duration = $holiday['duration'];
                if ($duration === 'full_day' || ($duration === 'morning' && $period['start']->hour < 12)
                    || ($duration === 'afternoon' && $period['start']->hour >= 12)) {
                    $exempt = true;
                }
            }
            $exemptCount += (int) $exempt;
            if ($exempt && !$restDay) {
                $result['holiday_credit_minutes'] += (int) floor(($period['end']->timestamp - $period['start']->timestamp) / 60);
            }
            $holidayExempt = $exempt;
            $exempt = $exempt || $restDay;
            $finished = $now >= $period['end'];
            $invalid = $period['in'] && $period['out'] && $period['out'] <= $period['in'];
            $late = !$exempt && $period['in']
                ? max(0, (int) floor(($period['in']->timestamp - $period['start']->timestamp) / 60)) : 0;
            $under = !$exempt && $finished && !$invalid && $period['out']
                ? max(0, (int) floor(($period['end']->timestamp - $period['out']->timestamp) / 60)) : 0;
            $inState = $period['in'] ? 'Recorded' : ($exempt ? 'Exempt' : ($finished ? 'Missing' : 'Pending'));
            $outState = $period['out'] ? 'Recorded' : ($exempt ? 'Exempt' : ($finished ? 'Missing' : 'Pending'));
            $result['missing_count'] += (int) ($inState === 'Missing') + (int) ($outState === 'Missing') + (int) $invalid;
            $result['pending'] = $result['pending'] || (!$exempt && !$finished);
            $result['late_minutes'] += $late;
            $result['undertime_minutes'] += $under;
            if ($period['in'] && (!$result['first_in'] || $period['in']->format('Y-m-d H:i:s') < $result['first_in'])) {
                $result['first_in'] = $period['in']->format('Y-m-d H:i:s');
            }
            $result['periods'][] = [
                'sequence' => $schedules[$period['index']]['sequence'],
                'scheduled_in' => $period['start']->format('H:i'),
                'scheduled_out' => $period['end']->format('H:i'),
                'next_day' => !$period['start']->isSameDay($period['end']),
                'in' => $period['in']?->format('Y-m-d H:i:s'),
                'out' => $period['out']?->format('Y-m-d H:i:s'),
                'in_state' => $inState, 'out_state' => $outState,
                'late_minutes' => $late, 'undertime_minutes' => $under,
                'exempt' => $exempt, 'holiday_exempt' => $holidayExempt, 'rest_day' => $restDay, 'needs_review' => (bool) $invalid,
            ];
        }
        $result['status'] = $restDay ? 'Rest day' : ($exemptCount === count($result['periods']) ? 'Holiday'
            : ($result['missing_count'] ? 'Needs review' : ($result['pending'] ? 'Provisional' : 'Complete')));
        if ($restDay) $result['missing_count'] = 0;

        return $result;
    }
}
