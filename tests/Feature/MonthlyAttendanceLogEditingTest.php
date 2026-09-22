<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\AttendanceDatabaseTestCase;

class MonthlyAttendanceLogEditingTest extends AttendanceDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('biometric_log_overrides', function (Blueprint $table) {
            $table->dateTime('old_checktime')->nullable();
            $table->string('old_checktype')->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamps();
        });
        $this->actingAs((new User)->forceFill(['id' => 1, 'role' => 1]));
    }

    public function test_override_edit_and_remove_recalculate_reports_without_changing_device_log(): void
    {
        DB::table('checkinout')->insert(['id' => 1, 'USERID' => 1, 'CHECKTIME' => '2026-09-22 08:05:00', 'CHECKTYPE' => 'I']);
        $response = $this->postJson('/api/user/checkinout/override/store', [
            'user_id' => 1, 'action_type' => 'override', 'checkinout_id' => 1,
            'new_checktime' => '2026-09-22T08:20:00', 'new_checktype' => 'I',
            'year' => 2026, 'month' => 9,
        ])->assertOk()->assertJsonPath('override.created_by', 1);
        $id = $response->json('override.id');
        $this->postJson('/api/report/monthly-attendance', ['office_shift_id' => 1])->assertOk()
            ->assertJsonPath('employees.0.late_minutes', 20)->assertJsonPath('employees.0.late_count', 1);
        $this->postJson('/api/report/daily-attendance', ['date' => '2026-09-22', 'office_shift_id' => 1])->assertOk()
            ->assertJsonPath('summary.late_minutes', 20);
        $this->postJson('/api/user/checkinout/override/update', ['id' => $id,
            'new_checktime' => '2026-09-22T08:00:00', 'new_checktype' => 'I', 'year' => 2026, 'month' => 9])->assertOk();
        $this->postJson('/api/report/monthly-attendance', ['office_shift_id' => 1])->assertOk()
            ->assertJsonPath('employees.0.late_minutes', 0)->assertJsonPath('employees.0.late_count', 0);
        $this->postJson('/api/user/checkinout/override/delete', ['id' => $id, 'year' => 2026, 'month' => 9])->assertOk();
        $this->postJson('/api/report/monthly-attendance', ['office_shift_id' => 1])->assertOk()
            ->assertJsonPath('employees.0.late_minutes', 5);
        self::assertSame('2026-09-22 08:05:00', DB::table('checkinout')->where('id', 1)->value('CHECKTIME'));
    }

    public function test_adding_logs_fills_blank_in_and_out_cells(): void
    {
        foreach ([['08:12', 'I'], ['11:50', 'O']] as [$time, $type]) {
            $this->postJson('/api/user/checkinout/override/store', ['user_id' => 1, 'action_type' => 'add',
                'new_checktime' => '2026-09-22T'.$time.':00', 'new_checktype' => $type,
                'year' => 2026, 'month' => 9])->assertOk();
        }
        $this->postJson('/api/report/monthly-attendance', ['office_shift_id' => 1])->assertOk()
            ->assertJsonPath('employees.0.late_minutes', 12)->assertJsonPath('employees.0.undertime_minutes', 10)
            ->assertJsonPath('employees.0.total_minutes', 22);
        $this->postJson('/api/user/checkinout', ['user_id' => 1, 'year' => 2026, 'month' => 9])->assertOk()
            ->assertJsonCount(2, 'checkinouts')->assertJsonCount(2, 'overrides');
        self::assertSame(0, DB::table('checkinout')->count());
    }

    public function test_adding_overnight_departure_at_month_boundary_updates_previous_month(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-02 10:00:00', 'Asia/Manila'));
        DB::table('office_shift_schedules')->where('office_shift_id', 1)->delete();
        DB::table('office_shift_schedules')->insert(['office_shift_id' => 1, 'sequence' => 1,
            'time_in' => '22:00', 'time_out' => '06:00', 'is_next_day' => true]);
        $this->postJson('/api/user/checkinout/override/store', ['user_id' => 1, 'action_type' => 'add',
            'new_checktime' => '2026-10-01T05:45:00', 'new_checktype' => 'O', 'year' => 2026, 'month' => 9])->assertOk();
        $this->postJson('/api/report/monthly-attendance', ['month' => '2026-09', 'office_shift_id' => 1])->assertOk()
            ->assertJsonPath('employees.0.undertime_minutes', 15);
    }

    public function test_existing_log_endpoints_reject_non_admins_and_other_employees_device_logs(): void
    {
        DB::table('checkinout')->insert(['id' => 1, 'USERID' => 2, 'CHECKTIME' => '2026-09-22 08:00:00', 'CHECKTYPE' => 'I']);
        $payload = ['user_id' => 1, 'action_type' => 'override', 'checkinout_id' => 1,
            'new_checktime' => '2026-09-22T08:10:00', 'new_checktype' => 'I'];
        $this->postJson('/api/user/checkinout/override/store', $payload)->assertUnprocessable();
        $this->actingAs((new User)->forceFill(['id' => 2, 'role' => 0]));
        foreach (['store', 'update', 'delete'] as $action) {
            $this->postJson('/api/user/checkinout/override/'.$action, $payload)->assertForbidden();
        }
        self::assertSame(0, DB::table('biometric_log_overrides')->count());
    }
}
