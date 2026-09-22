<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class MonthlyAttendanceService
{
    public function __construct(private DailyAttendanceService $daily) {}

    public function calculate(CarbonImmutable $month, ?array $shift, array $punches, array $holidaysByDate, array $absencesByDate, CarbonImmutable $now, array $schedulesByDate = []): array
    {
        $byDate = [];
        foreach ($punches as $punch) {
            $byDate[substr($punch['time'], 0, 10)][] = $punch;
        }
        $result = ['days' => [], 'late_minutes' => 0, 'undertime_minutes' => 0,
            'late_count' => 0, 'undertime_count' => 0, 'late_days' => 0, 'undertime_days' => 0,
            'review_days' => 0, 'total_minutes' => 0, 'holiday_credit_minutes' => 0];
        for ($day = 1; $day <= $month->daysInMonth; $day++) {
            $date = $month->day($day);
            $dateKey = $date->toDateString();
            $resolved = $schedulesByDate[$dateKey] ?? $shift;
            $events = $resolved['_holidays'] ?? ($holidaysByDate[$dateKey] ?? []);
            $restDay = ($resolved['_is_working_day'] ?? null) === false;
            $attendance = $this->daily->calculate($dateKey, $resolved,
                array_merge($byDate[$dateKey] ?? [], $byDate[$date->addDay()->toDateString()] ?? []),
                $events, $now);
            $absence = $absencesByDate[$dateKey] ?? null;
            if ($absence && !$restDay) {
                if ($absence['status'] === 'filed') {
                    foreach ($attendance['periods'] as &$period) {
                        if (!$this->absenceAppliesToPeriod($absence, $period)) {
                            continue;
                        }
                        $period['late_minutes'] = 0;
                        $period['undertime_minutes'] = 0;
                        $period['in_state'] = 'Filed absence';
                        $period['out_state'] = 'Filed absence';
                        $period['exempt'] = true;
                        $period['needs_review'] = false;
                    }
                    unset($period);
                    $attendance['late_minutes'] = array_sum(array_column($attendance['periods'], 'late_minutes'));
                    $attendance['undertime_minutes'] = array_sum(array_column($attendance['periods'], 'undertime_minutes'));
                    $attendance['missing_count'] = count(array_filter($attendance['periods'], fn ($period) => $period['needs_review']));
                    $attendance['status'] = 'Filed '.$this->absenceLabel($absence).' absence';
                } elseif ($absence['status'] === 'unfiled') {
                    foreach ($attendance['periods'] as &$period) {
                        if (!$this->absenceAppliesToPeriod($absence, $period) || $period['in'] || $period['out'] || $period['exempt']) {
                            continue;
                        }
                        $start = CarbonImmutable::parse($dateKey.' '.$period['scheduled_in'], $now->timezone);
                        $end = CarbonImmutable::parse($dateKey.' '.$period['scheduled_out'], $now->timezone);
                        if ($period['next_day']) {
                            $end = $end->addDay();
                        }
                        $minutes = max(0, $start->diffInMinutes($end));
                        $period['late_minutes'] = $minutes;
                        $period['undertime_minutes'] = 0;
                        $period['in_state'] = 'Unfiled absence';
                        $period['out_state'] = 'Unfiled absence';
                        $period['needs_review'] = false;
                    }
                    unset($period);
                    $attendance['late_minutes'] = array_sum(array_column($attendance['periods'], 'late_minutes'));
                    $attendance['undertime_minutes'] = array_sum(array_column($attendance['periods'], 'undertime_minutes'));
                    $attendance['missing_count'] = count(array_filter($attendance['periods'], fn ($period) => $period['needs_review']));
                    $attendance['status'] = 'Unfiled '.$this->absenceLabel($absence).' absence';
                }
            }
            // This monthly report excludes Saturday/Sunday by the shift's start date.
            // Keep periods and original punches available for the log editor.
            if ($restDay || (($resolved['_is_working_day'] ?? null) === null && $date->isWeekend())) {
                $attendance['holiday_credit_minutes'] = 0;
                $attendance['late_minutes'] = 0;
                $attendance['undertime_minutes'] = 0;
                $attendance['missing_count'] = 0;
                $attendance['status'] = $attendance['status'] === 'Holiday' ? 'Holiday' : ($restDay ? 'Rest day' : 'Weekend');
                foreach ($attendance['periods'] as &$period) {
                    $period['late_minutes'] = 0;
                    $period['undertime_minutes'] = 0;
                    $period['needs_review'] = false;
                    foreach (['in_state', 'out_state'] as $state) {
                        if (in_array($period[$state], ['Missing', 'Pending'], true)) {
                            $period[$state] = 'Weekend';
                        }
                    }
                }
                unset($period);
            }
            $result['holiday_credit_minutes'] += $attendance['holiday_credit_minutes'];
            $result['late_minutes'] += $attendance['late_minutes'];
            $result['undertime_minutes'] += $attendance['undertime_minutes'];
            $result['late_days'] += (int) ($attendance['late_minutes'] > 0);
            $result['undertime_days'] += (int) ($attendance['undertime_minutes'] > 0);
            $result['review_days'] += (int) ($attendance['missing_count'] > 0);
            foreach ($attendance['periods'] as $period) {
                $result['late_count'] += (int) ($period['late_minutes'] > 0);
                $result['undertime_count'] += (int) ($period['undertime_minutes'] > 0);
            }
            // Keep the monthly payload compact; full punch details remain on daily monitoring.
            $result['days'][] = [
                'date' => $dateKey, 'status' => $attendance['status'],
                'absence' => $absence, 'rest_day' => $restDay, 'holidays' => $events,
                'working_day' => $resolved['_is_working_day'] ?? null,
                'schedule_name' => $resolved['name'] ?? null, 'schedule_source' => $resolved['_source'] ?? null,
                'holiday_credit_minutes' => $attendance['holiday_credit_minutes'],
                'periods' => array_map(fn ($period) => array_intersect_key($period, array_flip([
                    'sequence', 'scheduled_in', 'scheduled_out', 'next_day', 'late_minutes',
                    'undertime_minutes', 'in_state', 'out_state', 'exempt', 'needs_review', 'rest_day', 'holiday_exempt',
                ])), $attendance['periods']),
            ];
        }
        $result['total_minutes'] = $result['late_minutes'] + $result['undertime_minutes'];

        return $result;
    }

    private function absenceAppliesToPeriod(array $absence, array $period): bool
    {
        return match ($absence['duration'] ?? 'whole_day') {
            'morning' => $period['scheduled_in'] < '12:00',
            'afternoon' => $period['scheduled_in'] >= '12:00',
            default => true,
        };
    }

    private function absenceLabel(array $absence): string
    {
        return str_replace('_', ' ', $absence['duration'] ?? 'whole day');
    }
}
