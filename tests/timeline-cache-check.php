<?php
require __DIR__.'/../vendor/autoload.php';

use App\Services\DailyAttendanceService;
use Carbon\CarbonImmutable;

$now = CarbonImmutable::parse('2026-10-13 20:00:00', 'Asia/Manila');
$shift = ['schedules' => [
    ['sequence' => 1, 'time_in' => '08:00', 'time_out' => '12:00'],
    ['sequence' => 2, 'time_in' => '13:00', 'time_out' => '17:00'],
]];
$cached = new DailyAttendanceService;
foreach ([[], [['time' => '2026-10-13 08:05:00', 'type' => 'I']], []] as $punches) {
    $actual = $cached->calculate('2026-10-13', $shift, $punches, [], $now);
    $expected = (new DailyAttendanceService)->calculate('2026-10-13', $shift, $punches, [], $now);
    if ($actual !== $expected) throw new RuntimeException('Cached results differ');
}
echo "Timeline cache isolation passed.\n";
