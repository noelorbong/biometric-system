<?php
require __DIR__.'/../vendor/autoload.php';

use App\Services\DailyAttendanceService;
use Carbon\CarbonImmutable;

$now = CarbonImmutable::parse('2026-10-28 09:52:00', 'Asia/Manila');
$shift = ['schedules' => [
    ['sequence' => 1, 'time_in' => '08:00', 'time_out' => '12:00'],
    ['sequence' => 2, 'time_in' => '13:00', 'time_out' => '17:00'],
]];
$punches = [
    ['time' => '2026-10-28 08:10:00', 'type' => 'I', 'corrected' => true],
    ['time' => '2026-10-28 12:00:00', 'type' => 'O', 'corrected' => true],
    ['time' => '2026-10-28 12:15:00', 'type' => 'I', 'corrected' => true],
    ['time' => '2026-10-28 16:45:00', 'type' => 'O', 'corrected' => true],
    ['time' => '2026-10-28 17:30:00', 'type' => 'O'],
];
$check = function ($expected, $actual, string $message): void {
    if ($expected !== $actual) throw new RuntimeException($message);
};
foreach ([false, true] as $autoCorrection) {
    $shift['enable_auto_correction'] = $autoCorrection;
    $service = new DailyAttendanceService;
    $row = $service->calculate('2026-10-28', $shift, $punches, [], $now);
    $check('2026-10-28 12:00:00', $row['periods'][0]['out'], 'Manual morning OUT missing');
    $check('2026-10-28 12:15:00', $row['periods'][1]['in'], 'Manual afternoon IN missing');
    $check('2026-10-28 16:45:00', $row['periods'][1]['out'], 'Manual OUT missing or future device punch included');
    $check(10, $row['late_minutes'], 'Incorrect lateness');
    $check(0, $row['undertime_minutes'], 'Undertime assessed before period ends');
    $check('Provisional', $row['status'], 'Active day must remain provisional');

    $devicePunches = array_map(fn ($p) => ['time' => $p['time'], 'type' => $p['type']], $punches);
    $row = $service->calculate('2026-10-28', $shift, $devicePunches, [], $now);
    $check(null, $row['periods'][0]['out'], 'Future device morning OUT included');
    $check(null, $row['periods'][1]['in'], 'Future device afternoon IN included');
    $check(null, $row['periods'][1]['out'], 'Future device afternoon OUT included');

    $row = $service->calculate('2026-10-28', $shift, array_slice($punches, 0, 4), [], $now->setTime(20, 0));
    $check(15, $row['undertime_minutes'], 'Completed period undertime missing');
    $check('Complete', $row['status'], 'Completed day status incorrect');
}
echo "Manual future punches passed with auto-correction enabled and disabled.\n";
