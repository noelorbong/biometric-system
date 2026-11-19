<?php

// Standalone integration checks using only a disposable in-memory database.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app->instance('env', 'testing');
config(['database.default' => 'monthly_test', 'database.connections.monthly_test' => [
    'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
], 'cache.default' => 'array', 'app.timezone' => 'Asia/Manila']);

use App\Models\User;
use App\Services\MonthlyAttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 20:00', 'Asia/Manila'));
foreach (['users', 'user_profiles', 'office_shifts', 'office_shift_schedules', 'checkinout', 'biometric_log_overrides', 'holidays', 'attendance_absences'] as $table) {
    Schema::create($table, function (Blueprint $schema) use ($table) {
        $schema->id();
        $schema->softDeletes();
        if ($table === 'users') {
            $schema->string('name'); $schema->boolean('status'); $schema->integer('office_shift_id')->nullable();
        } elseif ($table === 'user_profiles') {
            $schema->integer('user_id'); $schema->string('display_name')->nullable();
        } elseif ($table === 'office_shifts') {
            $schema->string('name'); $schema->boolean('is_flexible')->default(false); $schema->boolean('grace_enabled')->default(false);
        } elseif ($table === 'office_shift_schedules') {
            $schema->integer('office_shift_id'); $schema->integer('sequence'); $schema->string('time_in'); $schema->string('time_out'); $schema->boolean('is_next_day')->default(false);
        } elseif ($table === 'checkinout') {
            $schema->integer('USERID'); $schema->dateTime('CHECKTIME'); $schema->string('CHECKTYPE');
        } elseif ($table === 'biometric_log_overrides') {
            $schema->integer('user_id'); $schema->integer('checkinout_id')->nullable(); $schema->string('action_type'); $schema->dateTime('new_checktime'); $schema->string('new_checktype');
        } elseif ($table === 'holidays') {
            $schema->date('holiday_date'); $schema->string('name'); $schema->string('duration'); $schema->boolean('is_working_day');
        } elseif ($table === 'attendance_absences') {
            $schema->integer('user_id'); $schema->date('absence_date'); $schema->string('status'); $schema->string('duration'); $schema->string('leave_type')->nullable(); $schema->string('remarks')->nullable();
        }
    });
}
(require __DIR__.'/../../database/migrations/2026_09_23_000001_create_work_schedule_rules.php')->up();
DB::table('office_shifts')->insert(['id' => 1, 'name' => 'Regular']);
DB::table('office_shift_schedules')->insert([
    ['office_shift_id' => 1, 'sequence' => 1, 'time_in' => '08:00', 'time_out' => '12:00'],
    ['office_shift_id' => 1, 'sequence' => 2, 'time_in' => '13:00', 'time_out' => '17:00'],
]);
for ($i = 1; $i <= 125; $i++) {
    DB::table('users')->insert(['id' => $i, 'name' => 'Employee '.str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'status' => true, 'office_shift_id' => 1]);
}
DB::table('users')->insert(['id' => 126, 'name' => 'Inactive', 'status' => false, 'office_shift_id' => 1]);
DB::table('checkinout')->insert(['USERID' => 125, 'CHECKTIME' => '2026-11-02 08:15:00', 'CHECKTYPE' => 'I']);

$attendance = new class(new App\Services\DailyAttendanceService) extends MonthlyAttendanceService {
    public int $calls = 0;
    public function calculate(CarbonImmutable $month, ?array $shift, array $punches, array $holidaysByDate, array $absencesByDate, CarbonImmutable $now, array $schedulesByDate = []): array
    {
        $this->calls++;
        return parent::calculate($month, $shift, $punches, $holidaysByDate, $absencesByDate, $now, $schedulesByDate);
    }
};
$controller = new App\Http\Controllers\MonthlyAttendanceController;
$request = function (array $payload, int $role = 1) use ($controller, $attendance) {
    $request = Request::create('/api/report/monthly-attendance', 'POST', ['month' => '2026-11'] + $payload);
    $request->setUserResolver(fn () => (new User)->forceFill(['id' => 1, 'role' => $role]));
    return $controller->index($request, $attendance, new App\Services\AttendanceLogService, new App\Services\WorkScheduleResolver)->getData(true);
};
$first = $request(['per_page' => 50, 'page' => 1]);
check(count($first['employees']) === 50 && $first['pagination']['total'] === 125, 'Page size/active-user count incorrect.');
check($first['summary']['minutes'] === 15 && $first['summary']['late'] === 1, 'Totals must cover the entire filtered report.');
check($first['employees'][0]['id'] === 125, 'Default global sorting must include employees outside the first ID page.');
$calls = $attendance->calls;
$second = $request(['per_page' => 50, 'page' => 2]);
check($attendance->calls === $calls, 'Paging must reuse the cached calculation.');
check(count($second['employees']) === 50 && $second['pagination']['from'] === 51, 'Second page incorrect.');
check(!array_intersect(array_column($first['employees'], 'id'), array_column($second['employees'], 'id')), 'Pages must not duplicate users.');
check($second['periods'] === $first['periods'] && $second['excluded_dates'] === $first['excluded_dates'], 'Columns must remain stable across pages.');
check($first['excluded_dates']['2026-11-01'] === true && $first['excluded_dates']['2026-11-02'] === false, 'Weekend and working-day metadata incorrect.');
$last = $request(['per_page' => 50, 'page' => 999]);
check($last['pagination']['page'] === 3 && count($last['employees']) === 25, 'Out-of-range page must clamp safely.');
$search = $request(['per_page' => 50, 'search' => 'Employee 125']);
check($search['pagination']['total'] === 1 && $search['employees'][0]['id'] === 125, 'Search must operate across all employees.');
$issues = $request(['per_page' => 50, 'issues_only' => true]);
check($issues['pagination']['total'] === 1 && $issues['summary']['minutes'] === 15, 'Issue filtering/global totals incorrect.');
$export = $request(['export_all' => true, 'per_page' => 50]);
check(count($export['employees']) === 125 && !isset($export['pagination']), 'Full exports must not be paginated.');
check($export['summary'] === $first['summary'], 'Full export and paginated totals must match.');
$legacy = $request([]);
check(count($legacy['employees']) === 125 && !isset($legacy['pagination']), 'Existing unpaginated report clients must keep the full response.');
$none = $request(['per_page' => 50, 'search' => 'No such employee']);
check($none['pagination']['from'] === 0 && $none['pagination']['to'] === 0 && $none['summary']['count'] === 0, 'Empty searches need safe pagination and zero totals.');
DB::table('checkinout')->where('USERID', 125)->update(['CHECKTIME' => '2026-11-02 08:25:00']);
$fresh = $request(['per_page' => 50, 'refresh' => true]);
check($fresh['summary']['minutes'] === 25 && $attendance->calls > $calls, 'Refresh must bypass cached data.');
try {
    $request(['per_page' => 50], 0);
    throw new RuntimeException('Non-admin report access was allowed.');
} catch (Symfony\Component\HttpKernel\Exception\HttpException $e) {
    check($e->getStatusCode() === 403, 'Non-admin request must be forbidden.');
}
echo "Monthly pagination, global filters/totals, stable columns, full exports, cache reuse, refresh and authorization checks passed.\n";
