<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\User;
use App\Models\WorkScheduleRule;
use App\Models\WorkSuspension;
use Carbon\CarbonImmutable;

class WorkScheduleResolver
{
    private array $rules = [];
    private array $events = [];

    public static function profiles(): array
    {
        return [
            'compressed' => ['name' => 'Compressed — 10 hours', 'working_days' => [1, 2, 3, 4],
                'friday_exception' => true, 'is_flexible' => false, 'schedules' => [
                    ['sequence' => 1, 'time_in' => '07:00', 'time_out' => '12:00', 'is_next_day' => false],
                    ['sequence' => 2, 'time_in' => '13:00', 'time_out' => '18:00', 'is_next_day' => false],
                ]],
            'standard' => ['name' => 'Standard — 8 hours', 'working_days' => [1, 2, 3, 4],
                'friday_exception' => false, 'is_flexible' => false, 'schedules' => [
                    ['sequence' => 1, 'time_in' => '08:00', 'time_out' => '12:00', 'is_next_day' => false],
                    ['sequence' => 2, 'time_in' => '13:00', 'time_out' => '17:00', 'is_next_day' => false],
                ]],
        ];
    }

    public function load(CarbonImmutable $from, CarbonImmutable $to): self
    {
        $this->rules = WorkScheduleRule::where('effective_from', '<=', $to->toDateString())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from->toDateString()))
            ->get()->toArray();
        $this->events = [];
        foreach (Holiday::whereBetween('holiday_date', [$from->toDateString(), $to->toDateString()])->get() as $holiday) {
            $this->events[$holiday->holiday_date->format('Y-m-d')][] = $holiday->toArray();
        }
        foreach (WorkSuspension::whereBetween('suspension_date', [$from->toDateString(), $to->toDateString()])->get() as $suspension) {
            $this->events[$suspension->suspension_date][] = ['id' => 'suspension-'.$suspension->id,
                'name' => $suspension->name, 'holiday_date' => $suspension->suspension_date,
                'duration' => $suspension->duration, 'is_working_day' => false,
                'office_shift_id' => $suspension->office_shift_id, 'type' => 'suspension'];
        }
        return $this;
    }

    public function events(string $date, ?int $shiftId = null): array
    {
        return array_values(array_filter($this->events[$date] ?? [],
            fn ($event) => !$shiftId || empty($event['office_shift_id']) || (int) $event['office_shift_id'] === $shiftId));
    }

    public function resolve(User $user, string $date): ?array
    {
        $base = $user->officeShift?->toArray();
        $applicable = array_filter($this->rules, fn ($rule) =>
            (int) $rule['office_shift_id'] === (int) $user->office_shift_id
            && (!$rule['user_id'] || (int) $rule['user_id'] === (int) $user->id)
            && $rule['effective_from'] <= $date && (!$rule['effective_to'] || $rule['effective_to'] >= $date));
        $priority = fn ($rule) => ($rule['kind'] === 'weekly' ? 20 : ($rule['kind'] === 'baseline' ? 0 : 10)) + ($rule['user_id'] ? 1 : 0);
        usort($applicable, fn ($a, $b) => ($priority($b) <=> $priority($a))
            ?: strcmp($b['effective_from'], $a['effective_from']) ?: ($b['id'] <=> $a['id']));
        $rule = $applicable[0] ?? null;
        if ($rule) {
            $base = array_merge($base ?? [], $rule['profile']);
        }
        if (!$base) return null;
        $dateValue = CarbonImmutable::parse($date);
        $workingDays = $base['working_days'] ?? null;
        $base['_is_working_day'] = is_array($workingDays) ? in_array($dateValue->dayOfWeekIso, $workingDays, true) : null;
        $base['_rule_id'] = $rule['id'] ?? null;
        $base['_source'] = $rule ? ($rule['kind'] === 'weekly' ? 'Weekly exception' : ($rule['kind'] === 'baseline' ? 'Historical baseline' : 'Effective schedule')) : 'Office shift';
        $base['_reason'] = $rule['reason'] ?? null;
        $base['_holidays'] = $this->events($date, (int) $user->office_shift_id);
        $base['_holiday_credit_minutes'] = 0;
        if ($base['_is_working_day'] !== false) {
            foreach ($base['schedules'] ?? [] as $slot) {
                $start = CarbonImmutable::parse($date.' '.$slot['time_in']);
                $end = CarbonImmutable::parse($date.' '.$slot['time_out']);
                if (!empty($slot['is_next_day']) || $end <= $start) $end = $end->addDay();
                foreach ($base['_holidays'] as $event) {
                    if (!$event['is_working_day'] && ($event['duration'] === 'full_day'
                        || ($event['duration'] === 'morning' && $start->hour < 12)
                        || ($event['duration'] === 'afternoon' && $start->hour >= 12))) {
                        $base['_holiday_credit_minutes'] += (int) $start->diffInMinutes($end);
                        break;
                    }
                }
            }
        }
        return $base;
    }

    public function forMonth(User $user, CarbonImmutable $month): array
    {
        $dates = [];
        for ($day = 1; $day <= $month->daysInMonth; $day++) {
            $date = $month->day($day)->toDateString();
            $dates[$date] = $this->resolve($user, $date);
        }
        return $dates;
    }
}
