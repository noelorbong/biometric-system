<?php
require __DIR__.'/../vendor/autoload.php';

use App\Services\BiometricAutoCorrectionService;
use Carbon\CarbonImmutable;

$service = new BiometricAutoCorrectionService;
$schedule = [
    ['sequence' => 1, 'time_in' => '08:00', 'time_out' => '12:00'],
    ['sequence' => 2, 'time_in' => '13:00', 'time_out' => '17:00'],
];
$now = CarbonImmutable::parse('2026-10-13 20:00:00', 'Asia/Manila');
$punches = array_map(fn ($time) => ['time' => '2026-10-13 '.$time, 'type' => 'O'],
    ['07:46:55', '12:08:25', '12:31:37', '15:00:49']);
$result = $service->correct($punches, $schedule, '2026-10-13', $now);
$expected = ['2026-10-13 07:46:55', '2026-10-13 12:08:25', '2026-10-13 12:31:37', '2026-10-13 15:00:49'];
if ([$result[0]['in'], $result[0]['out'], $result[1]['in'], $result[1]['out']] !== $expected) {
    throw new RuntimeException('Four OUT punches did not resolve to the expected sessions.');
}
if ($result[1]['out_punch'] !== $punches[3]) throw new RuntimeException('Raw punch metadata changed.');
$punches[3]['time'] = '2026-10-13 14:59:49';
$result = $service->correct($punches, $schedule, '2026-10-13', $now);
if ($result[1]['out'] !== null) throw new RuntimeException('Before-midpoint behavior changed.');
$added = array_map(fn ($time) => ['time' => '2026-11-02 '.$time, 'type' => 'O', 'corrected' => true],
    ['07:30:00', '12:01:00', '12:13:00', '15:00:00']);
$now = CarbonImmutable::parse('2026-11-03 20:00:00', 'Asia/Manila');
$exact = $service->correct($added, $schedule, '2026-11-02', $now);
if ([$exact[0]['in'], $exact[0]['out'], $exact[1]['in'], $exact[1]['out']] !== array_column($added, 'time')) {
    throw new RuntimeException('Exact midpoint checkout was lost.');
}
if ($service->correct([$added[3]], $schedule, '2026-11-02', $now)[1]['out'] !== null) {
    throw new RuntimeException('Lone midpoint punch behavior changed.');
}
echo "Auto-correction midpoint checks passed.\n";
$lateSchedule = [
    ['sequence' => 1, 'time_in' => '10:15', 'time_out' => '12:00'],
    ['sequence' => 2, 'time_in' => '12:15', 'time_out' => '18:30'],
];
$latePunches = array_map(fn ($time) => ['time' => '2026-11-02 '.$time, 'type' => 'O', 'corrected' => true],
    ['11:13:00', '12:02:00', '12:09:00', '17:12:00']);
$late = $service->correct($latePunches, $lateSchedule, '2026-11-02', $now);
if ([$late[0]['in'], $late[0]['out'], $late[1]['in'], $late[1]['out']] !== array_column($latePunches, 'time')) {
    throw new RuntimeException('Late morning arrival was lost.');
}
$cluster = array_map(fn ($time) => ['time' => '2026-11-02 '.$time, 'type' => 'O'], ['11:55:00', '11:59:00']);
if ($service->correct($cluster, $lateSchedule, '2026-11-02', $now)[0]['in'] !== null
    || $service->correct([$latePunches[0]], $lateSchedule, '2026-11-02', $now)[0]['in'] !== null) {
    throw new RuntimeException('Cluster or single-punch behavior changed.');
}
echo "Late-arrival and duplicate-cluster checks passed.\n";
