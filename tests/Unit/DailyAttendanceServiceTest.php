<?php

namespace Tests\Unit;

use App\Services\DailyAttendanceService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class DailyAttendanceServiceTest extends TestCase
{
    private function shift(array $times = [['08:00', '12:00'], ['13:00', '17:00']]): array
    {
        return ['is_flexible' => false, 'grace_enabled' => false, 'schedules' => array_map(
            fn ($row, $i) => ['sequence' => $i + 1, 'time_in' => $row[0], 'time_out' => $row[1], 'is_next_day' => $row[1] <= $row[0]],
            $times, array_keys($times)
        )];
    }

    private function calculate(array $punches, ?array $shift = null, array $holidays = [], string $now = '2026-09-22 20:00:00'): array
    {
        return (new DailyAttendanceService)->calculate('2026-09-22', $shift ?? $this->shift(), array_map(
            fn ($p) => ['time' => strlen($p[0]) < 10 ? '2026-09-22 '.$p[0].':00' : $p[0], 'type' => $p[1]], $punches
        ), $holidays, CarbonImmutable::parse($now, 'Asia/Manila'));
    }

    public function test_regular_and_jo_lateness_is_accumulated_across_periods(): void
    {
        $regular = $this->calculate([['08:05', 'I'], ['12:00', 'O'], ['13:02', 'I'], ['17:00', 'O']]);
        self::assertSame(7, $regular['late_minutes']);
        self::assertSame(0, $regular['undertime_minutes']);
        self::assertSame('Complete', $regular['status']);
        $jo = $this->calculate([['05:12', 'I'], ['09:00', 'O'], ['10:03', 'I'], ['12:00', 'O'], ['13:00', 'I'], ['16:45', 'O']],
            $this->shift([['05:00', '09:00'], ['10:00', '12:00'], ['13:00', '17:00']]));
        self::assertSame(15, $jo['late_minutes']);
        self::assertSame(15, $jo['undertime_minutes']);
        self::assertCount(3, $jo['periods']);
    }

    public function test_missing_punches_do_not_shift_later_periods(): void
    {
        $row = $this->calculate([['08:00', 'I'], ['13:02', 'I'], ['17:00', 'O']]);
        self::assertNull($row['periods'][0]['out']);
        self::assertSame('2026-09-22 13:02:00', $row['periods'][1]['in']);
        self::assertSame(2, $row['late_minutes']);
        self::assertSame(0, $row['undertime_minutes']);
        self::assertSame(1, $row['missing_count']);
        self::assertSame('Needs review', $row['status']);
    }

    public function test_duplicates_keep_first_in_and_last_out_within_each_period(): void
    {
        $row = $this->calculate([['08:00', 'I'], ['08:03', 'I'], ['11:50', 'O'], ['12:00', 'O'], ['13:00', 'I'], ['17:00', 'O']]);
        self::assertSame(0, $row['late_minutes']);
        self::assertSame(0, $row['undertime_minutes']);
    }

    public function test_active_period_does_not_produce_premature_undertime(): void
    {
        $row = $this->calculate([['08:05', 'I'], ['11:00', 'O']], now: '2026-09-22 11:30:00');
        self::assertSame(5, $row['late_minutes']);
        self::assertSame(0, $row['undertime_minutes']);
        self::assertSame('Pending', $row['periods'][1]['in_state']);
        self::assertSame(0, $row['missing_count']);
        self::assertSame('Provisional', $row['status']);
    }

    public function test_overnight_shift_uses_next_day_checkout(): void
    {
        $row = $this->calculate([['22:05', 'I'], ['2026-09-23 05:45:00', 'O'], ['2026-09-23 22:15:00', 'I']],
            $this->shift([['22:00', '06:00']]), now: '2026-09-24 08:00:00');
        self::assertSame(5, $row['late_minutes']);
        self::assertSame(15, $row['undertime_minutes']);
        self::assertTrue($row['periods'][0]['next_day']);
    }

    public function test_holidays_exempt_only_affected_periods(): void
    {
        $punches = [['08:05', 'I'], ['12:00', 'O'], ['13:10', 'I'], ['16:45', 'O']];
        $row = $this->calculate($punches, holidays: [['duration' => 'morning', 'is_working_day' => false]]);
        self::assertSame(10, $row['late_minutes']);
        self::assertSame(15, $row['undertime_minutes']);
        $row = $this->calculate([], holidays: [['duration' => 'full_day', 'is_working_day' => false]]);
        self::assertSame('Holiday', $row['status']);
        self::assertSame(0, $row['missing_count']);
        $row = $this->calculate($punches, holidays: [['duration' => 'full_day', 'is_working_day' => true]]);
        self::assertSame(15, $row['late_minutes']);
    }

    public function test_schedule_grace_corrects_type_but_does_not_waive_lateness(): void
    {
        $shift = array_merge($this->shift(), ['grace_enabled' => true, 'grace_before_minutes' => 10, 'grace_after_minutes' => 10]);
        $row = $this->calculate([['08:05', 'O'], ['11:55', 'I'], ['13:00', 'I'], ['17:00', 'O']], $shift);
        self::assertSame(5, $row['late_minutes']);
        self::assertSame(5, $row['undertime_minutes']);
        self::assertSame(0, $row['missing_count']);
    }

    public function test_no_logs_and_flexible_shifts_are_not_automatically_absent(): void
    {
        $row = $this->calculate([]);
        self::assertSame('Needs review', $row['status']);
        self::assertSame(0, $row['undertime_minutes']);
        $row = $this->calculate([], ['is_flexible' => true, 'schedules' => []]);
        self::assertSame('Flexible shift', $row['status']);
        self::assertSame(0, $row['late_minutes']);
    }
}
