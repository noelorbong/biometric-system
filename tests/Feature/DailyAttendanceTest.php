<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\AttendanceDatabaseTestCase;

class DailyAttendanceTest extends AttendanceDatabaseTestCase
{
    public function test_access_and_input_validation(): void
    {
        $this->postJson('/api/report/daily-attendance')->assertUnauthorized();
        $this->actingAs(new User(['role' => 0]));
        $this->postJson('/api/report/daily-attendance')->assertForbidden();
        $this->admin();
        $this->postJson('/api/report/daily-attendance', ['date' => '2026-02-30'])->assertUnprocessable();
        $this->postJson('/api/report/daily-attendance', ['office_shift_id' => 999])->assertUnprocessable();
    }

    public function test_default_date_shift_filter_and_unique_employee_summary(): void
    {
        $this->admin();
        DB::table('checkinout')->insert([
            ['USERID' => 1, 'CHECKTIME' => '2026-09-22 08:05:00', 'CHECKTYPE' => 'I'],
            ['USERID' => 1, 'CHECKTIME' => '2026-09-22 12:00:00', 'CHECKTYPE' => 'O'],
            ['USERID' => 1, 'CHECKTIME' => '2026-09-22 13:02:00', 'CHECKTYPE' => 'I'],
            ['USERID' => 1, 'CHECKTIME' => '2026-09-22 16:45:00', 'CHECKTYPE' => 'O'],
        ]);
        $this->postJson('/api/report/daily-attendance', ['office_shift_id' => 1])->assertOk()
            ->assertJsonPath('date', '2026-09-22')->assertJsonCount(1, 'employees')
            ->assertJsonPath('summary.late_count', 1)->assertJsonPath('summary.late_minutes', 7)
            ->assertJsonPath('summary.undertime_count', 1)->assertJsonPath('summary.undertime_minutes', 15);
        $this->postJson('/api/report/daily-attendance')->assertOk()->assertJsonCount(2, 'employees');
    }

    public function test_corrections_replace_originals_even_when_moved_to_another_date(): void
    {
        $this->admin();
        DB::table('checkinout')->insert(['id' => 1, 'USERID' => 1, 'CHECKTIME' => '2026-09-22 08:30:00', 'CHECKTYPE' => 'I']);
        DB::table('biometric_log_overrides')->insert([
            ['user_id' => 1, 'checkinout_id' => 1, 'action_type' => 'override', 'new_checktime' => '2026-09-25 08:30:00', 'new_checktype' => 'I'],
            ['user_id' => 1, 'checkinout_id' => null, 'action_type' => 'add', 'new_checktime' => '2026-09-22 08:03:00', 'new_checktype' => 'I'],
        ]);
        $this->postJson('/api/report/daily-attendance', ['office_shift_id' => 1])->assertOk()
            ->assertJsonPath('summary.late_minutes', 3)->assertJsonCount(1, 'employees.0.punches')
            ->assertJsonPath('employees.0.punches.0.corrected', true);
    }
}
