<?php

namespace Tests\Support;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

abstract class AttendanceDatabaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Dedicated in-memory connection: never migrate or clear the application's database.
        config(['database.default' => 'attendance_test', 'database.connections.attendance_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ], 'app.timezone' => 'Asia/Manila']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-22 20:00:00', 'Asia/Manila'));
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
                    $schema->integer('user_id'); $schema->date('absence_date'); $schema->string('status'); $schema->string('duration')->default('whole_day'); $schema->string('leave_type')->nullable(); $schema->string('remarks')->nullable();
                }
            });
        }
        (require base_path('database/migrations/2026_09_23_000001_create_work_schedule_rules.php'))->up();
        DB::table('office_shifts')->insert([['id' => 1, 'name' => 'Regular'], ['id' => 2, 'name' => 'JO']]);
        DB::table('office_shift_schedules')->insert([
            ['office_shift_id' => 1, 'sequence' => 1, 'time_in' => '08:00', 'time_out' => '12:00'],
            ['office_shift_id' => 1, 'sequence' => 2, 'time_in' => '13:00', 'time_out' => '17:00'],
        ]);
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Juan', 'status' => true, 'office_shift_id' => 1],
            ['id' => 2, 'name' => 'Maria', 'status' => true, 'office_shift_id' => 2],
            ['id' => 3, 'name' => 'Inactive', 'status' => false, 'office_shift_id' => 1],
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        DB::purge('attendance_test');
        parent::tearDown();
    }

    protected function admin(): void
    {
        $this->actingAs(new User(['id' => 99, 'role' => 1]));
    }

}
