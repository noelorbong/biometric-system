<?php
require __DIR__.'/../vendor/autoload.php';

use App\Http\Controllers\OfficeShiftController;
use App\Models\OfficeShift;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
// All writes use a dedicated in-memory database.
config(['database.default' => 'shift_test', 'database.connections.shift_test' => [
    'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
]]);
foreach ([
    '2026_03_17_000010_create_office_shifts_table.php',
    '2026_03_17_000012_create_office_shift_schedules_table.php',
    '2026_07_20_000001_add_grace_settings_to_office_shifts_table.php',
    '2026_10_12_000001_add_split_grace_settings_to_office_shifts_table.php',
    '2026_10_13_000001_add_enable_auto_correction_to_office_shifts_table.php',
] as $file) {
    (require __DIR__.'/../database/migrations/'.$file)->up();
}
$migration = require __DIR__.'/../database/migrations/2026_10_28_000001_allow_reusing_deleted_office_shift_names.php';
$migration->up();
$migration->down();
$migration->up();
$controller = new OfficeShiftController;
$request = fn (array $data) => Request::create('/', 'POST', $data);
$check = function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$reject = function (callable $action) use ($check): void {
    try { $action(); } catch (ValidationException $e) {
        $check(isset($e->errors()['name']), 'Expected name validation error');
        return;
    }
    throw new RuntimeException('Duplicate active name accepted');
};
$old = OfficeShift::where('name', 'Utility')->firstOrFail();
$old->delete();
$created = $controller->store($request(['name' => 'Utility', 'is_flexible' => true]))->getData(true)['office_shift'];
$check($created['id'] !== $old->id, 'Deleted shift was reused instead of creating a new record');
$check(OfficeShift::withTrashed()->findOrFail($old->id)->trashed(), 'Deleted history lost');
$check(!array_key_exists('active_name', $created), 'Internal column exposed');
$reject(fn () => $controller->store($request(['name' => 'Utility', 'is_flexible' => true])));
$controller->update($request(['id' => $created['id'], 'name' => 'Utility', 'is_flexible' => true]));
$other = OfficeShift::where('name', 'Guard')->firstOrFail();
$reject(fn () => $controller->update($request(['id' => $other->id, 'name' => 'Utility', 'is_flexible' => true])));
OfficeShift::findOrFail($created['id'])->delete();
$controller->update($request(['id' => $other->id, 'name' => 'Utility', 'is_flexible' => true]));
$check(OfficeShift::withTrashed()->where('name', 'Utility')->count() === 3, 'Historical names not preserved');
try {
    DB::table('office_shifts')->insert(['name' => 'Utility']);
    throw new RuntimeException('Database allowed duplicate active names');
} catch (QueryException $e) {
    $check(str_contains($e->getMessage(), 'UNIQUE constraint failed'), 'Unexpected database failure');
}
echo "Office shift name reuse, active duplicate protection, and migration round-trip passed.\n";
