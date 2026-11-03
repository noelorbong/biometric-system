<?php
require __DIR__.'/../vendor/autoload.php';

use App\Http\Controllers\BiometricLogController;
use App\Models\Checkinout;
use App\Models\User;
use App\Services\AttendanceLogService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'log_test', 'database.connections.log_test' => [
    'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
]]);
Schema::create('users', function (Blueprint $table) {
    $table->id(); $table->string('name'); $table->softDeletes();
});
Schema::create('user_profiles', function (Blueprint $table) {
    $table->id(); $table->integer('user_id'); $table->softDeletes();
    foreach (['display_name', 'first_name', 'middle_name', 'last_name', 'name_extension'] as $column) $table->string($column)->nullable();
});
foreach (['2026_03_17_000001_create_checkinout_table.php', '2026_03_24_000001_create_biometric_log_overrides_table.php', '2026_02_09_082436_create_activity_logs_table.php'] as $file) {
    (require __DIR__.'/../database/migrations/'.$file)->up();
}
DB::table('users')->insert(['id' => 1, 'name' => 'Test Employee']);
DB::table('checkinout')->insert(['id' => 1, 'USERID' => 1, 'CHECKTIME' => '2026-11-02 08:00:00', 'CHECKTYPE' => 'I']);
DB::table('biometric_log_overrides')->insert(['user_id' => 1, 'checkinout_id' => 1, 'action_type' => 'override', 'new_checktime' => '2026-11-02 08:05:00', 'new_checktype' => 'I']);
$controller = new BiometricLogController;
$request = function (array $data, int $role = 1) {
    $request = Request::create('/', 'POST', $data);
    $request->setUserResolver(fn () => (new User)->forceFill(['id' => 1, 'role' => $role]));
    return $request;
};
$check = function ($condition, $message) { if (!$condition) throw new RuntimeException($message); };
$list = fn ($tab) => $controller->index($request(['tab' => $tab]))->getData(true)['logs']['data'];
$attendance = fn () => (new AttendanceLogService)->forUsers([1], CarbonImmutable::parse('2026-11-02'), CarbonImmutable::parse('2026-11-03'));
foreach (['delete', 'restore'] as $method) {
    try {
        $controller->$method($request(['id' => 1], 6));
        throw new RuntimeException('Unauthorized mutation accepted');
    } catch (HttpException $e) { $check($e->getStatusCode() === 403, 'Wrong authorization response'); }
}
$check(count($list('logs')) === 1 && count($list('overrides')) === 0, 'Initial listing wrong');
$check($attendance()[1][0]['time'] === '2026-11-02 08:05:00', 'Override missing initially');
$controller->delete($request(['id' => 1]));
$controller->delete($request(['id' => 1]));
$check(Checkinout::withTrashed()->findOrFail(1)->trashed(), 'Raw record not preserved');
$check(count($list('logs')) === 0 && count($list('overrides')) === 1, 'Deleted listing wrong');
$check($attendance() === [], 'Deleted punch or its correction still counted');
$dayLogs = $controller->deletedForDay($request(['user_id' => 1, 'date' => '2026-11-02']))->getData(true)['logs'];
$check(count($dayLogs) === 1 && $dayLogs[0]['_deleted'] === true, 'Modal deleted punch missing');
$check($controller->deletedForDay($request(['user_id' => 2, 'date' => '2026-11-02']))->getData(true)['logs'] === [], 'Other user punches leaked');
$check($controller->deletedForDay($request(['user_id' => 1, 'date' => '2026-11-03']))->getData(true)['logs'] === [], 'Other day punches included');
try {
    $controller->deletedForDay($request(['user_id' => 1, 'date' => '2026-11-02'], 6));
    throw new RuntimeException('Unauthorized deleted-log access accepted');
} catch (HttpException $e) { $check($e->getStatusCode() === 403, 'Wrong access response'); }
$controller->restore($request(['id' => 1]));
$controller->restore($request(['id' => 1]));
$check(count($list('logs')) === 1 && count($list('overrides')) === 0, 'Restored listing wrong');
$check($attendance()[1][0]['time'] === '2026-11-02 08:05:00', 'Correction not restored');
$check(DB::table('activity_logs')->where('action', 'Deleted')->count() === 1, 'Delete audit missing');
$check(DB::table('activity_logs')->where('action', 'Restored')->count() === 1, 'Restore audit missing');
echo "Delete/restore, permissions, listings, attendance exclusion, and audit checks passed.\n";
DB::table('biometric_log_overrides')->insert([
    'user_id' => 1, 'action_type' => 'add', 'new_checktime' => '2026-11-02 17:00:00',
    'new_checktype' => 'O', 'created_by' => 1, 'deleted_at' => '2026-11-03 09:00:00',
]);
$corrections = $controller->index($request(['tab' => 'overrides', 'override_view' => 'corrections']))->getData(true)['logs']['data'];
$check(count($corrections) === 2, 'Correction history missing');
$check($corrections[0]['action_type'] === 'add' && $corrections[0]['status'] === 'Removed', 'Removed addition not displayed');
$check($corrections[0]['created_by'] === 'Test Employee', 'Actor missing');
$check($corrections[1]['action_type'] === 'override', 'Override missing');
$filtered = $controller->index($request(['tab' => 'overrides', 'override_view' => 'corrections', 'checktype' => 'I', 'sort_by' => 'CHECKTIME']))->getData(true)['logs']['data'];
$check(count($filtered) === 1 && $filtered[0]['checktime'] === '2026-11-02 08:05:00', 'Correction filtering failed');
echo "Correction history, removed additions, actors and type filtering passed.\n";
