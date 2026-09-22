<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Support\AttendanceDatabaseTestCase;

class MonthlyAttendanceTest extends AttendanceDatabaseTestCase
{
    public function test_weekend_punches_and_missing_records_are_excluded_from_monthly_totals(): void
    {
        $this->admin();
        DB::table('checkinout')->insert([
            ['USERID' => 1, 'CHECKTIME' => '2026-09-19 08:20:00', 'CHECKTYPE' => 'I'],
            ['USERID' => 1, 'CHECKTIME' => '2026-09-19 11:45:00', 'CHECKTYPE' => 'O'],
        ]);
        $this->postJson('/api/report/monthly-attendance', ['office_shift_id' => 1])->assertOk()
            ->assertJsonPath('employees.0.total_minutes', 0)
            ->assertJsonPath('employees.0.late_count', 0)
            ->assertJsonPath('employees.0.undertime_count', 0)
            ->assertJsonPath('employees.0.days.18.status', 'Weekend')
            ->assertJsonPath('employees.0.days.18.periods.1.in_state', 'Weekend')
            ->assertJsonPath('employees.0.days.19.periods.0.out_state', 'Weekend');
        // Daily monitoring remains an account of the actual scheduled punches.
        $this->postJson('/api/report/daily-attendance', ['date' => '2026-09-19', 'office_shift_id' => 1])->assertOk()
            ->assertJsonPath('summary.late_minutes', 20)->assertJsonPath('summary.undertime_minutes', 15);
    }

    public function test_partial_and_working_holidays_keep_non_exempt_periods_assessed(): void
    {
        $this->admin();
        DB::table('holidays')->insert([
            ['holiday_date' => '2026-09-21', 'name' => 'Morning holiday', 'duration' => 'morning', 'is_working_day' => false],
            ['holiday_date' => '2026-09-22', 'name' => 'Working holiday', 'duration' => 'full_day', 'is_working_day' => true],
        ]);
        DB::table('checkinout')->insert([
            ['USERID' => 1, 'CHECKTIME' => '2026-09-21 08:20:00', 'CHECKTYPE' => 'I'],
            ['USERID' => 1, 'CHECKTIME' => '2026-09-21 13:10:00', 'CHECKTYPE' => 'I'],
            ['USERID' => 1, 'CHECKTIME' => '2026-09-22 08:05:00', 'CHECKTYPE' => 'I'],
        ]);
        $this->postJson('/api/report/monthly-attendance', ['office_shift_id' => 1])->assertOk()
            ->assertJsonPath('employees.0.total_minutes', 15)
            ->assertJsonPath('employees.0.late_count', 2)
            ->assertJsonPath('employees.0.days.20.periods.0.exempt', true)
            ->assertJsonPath('employees.0.days.20.periods.1.exempt', false)
            ->assertJsonPath('employees.0.days.21.periods.0.exempt', false);
    }

    public function test_access_validation_and_calendar_month_lengths(): void
    {
        $this->postJson('/api/report/monthly-attendance')->assertUnauthorized();
        $this->actingAs(new User(['role' => 0]));
        $this->postJson('/api/report/monthly-attendance')->assertForbidden();
        $this->admin();
        $this->postJson('/api/report/monthly-attendance', ['month' => '2026-13'])->assertUnprocessable();
        $this->postJson('/api/report/monthly-attendance', ['office_shift_id' => 99])->assertUnprocessable();
        $this->postJson('/api/report/monthly-attendance')->assertOk()
            ->assertJsonPath('month', '2026-09')->assertJsonCount(30, 'days')->assertJsonCount(2, 'employees')
            ->assertJsonPath('days.4.weekend', true)->assertJsonPath('provisional', true);
        $this->postJson('/api/report/monthly-attendance', ['month' => '2024-02'])->assertOk()->assertJsonCount(29, 'days');
        $this->postJson('/api/report/monthly-attendance', ['month' => '2025-02'])->assertOk()->assertJsonCount(28, 'days');
    }

    public function test_period_counts_and_totals_match_daily_calculations_with_holidays_and_corrections(): void
    {
        $this->admin();
        DB::table('checkinout')->insert([
            ['id' => 1, 'USERID' => 1, 'CHECKTIME' => '2026-09-21 08:05:00', 'CHECKTYPE' => 'I'],
            ['id' => 2, 'USERID' => 1, 'CHECKTIME' => '2026-09-21 11:50:00', 'CHECKTYPE' => 'O'],
            ['id' => 3, 'USERID' => 1, 'CHECKTIME' => '2026-09-21 13:02:00', 'CHECKTYPE' => 'I'],
            ['id' => 4, 'USERID' => 1, 'CHECKTIME' => '2026-09-21 16:45:00', 'CHECKTYPE' => 'O'],
            ['id' => 5, 'USERID' => 1, 'CHECKTIME' => '2026-09-20 08:30:00', 'CHECKTYPE' => 'I'],
        ]);
        DB::table('biometric_log_overrides')->insert([
            'user_id' => 1, 'checkinout_id' => 3, 'action_type' => 'override',
            'new_checktime' => '2026-09-21 13:04:00', 'new_checktype' => 'I',
        ]);
        DB::table('holidays')->insert(['holiday_date' => '2026-09-20', 'name' => 'Holiday', 'duration' => 'full_day', 'is_working_day' => false]);
        $monthly = $this->postJson('/api/report/monthly-attendance', ['office_shift_id' => 1])->assertOk()
            ->assertJsonCount(1, 'employees')->assertJsonPath('employees.0.late_count', 2)
            ->assertJsonPath('employees.0.undertime_count', 2)->assertJsonPath('employees.0.late_days', 1)
            ->assertJsonPath('employees.0.late_minutes', 9)->assertJsonPath('employees.0.undertime_minutes', 25)
            ->assertJsonPath('employees.0.total_minutes', 34)->assertJsonPath('employees.0.days.19.status', 'Holiday')
            ->assertJsonPath('employees.0.days.22.periods.0.in_state', 'Pending');
        $daily = $this->postJson('/api/report/daily-attendance', ['date' => '2026-09-21', 'office_shift_id' => 1])->assertOk();
        self::assertSame($daily->json('summary.late_minutes'), $monthly->json('employees.0.late_minutes'));
        self::assertSame($daily->json('summary.undertime_minutes'), $monthly->json('employees.0.undertime_minutes'));
    }

    public function test_jo_keeps_three_periods_and_counts_each_one(): void
    {
        $this->admin();
        DB::table('office_shift_schedules')->insert([
            ['office_shift_id' => 2, 'sequence' => 1, 'time_in' => '05:00', 'time_out' => '09:00'],
            ['office_shift_id' => 2, 'sequence' => 2, 'time_in' => '10:00', 'time_out' => '12:00'],
            ['office_shift_id' => 2, 'sequence' => 3, 'time_in' => '13:00', 'time_out' => '17:00'],
        ]);
        foreach (['05:12', '10:03', '13:01'] as $time) {
            DB::table('checkinout')->insert(['USERID' => 2, 'CHECKTIME' => '2026-09-22 '.$time.':00', 'CHECKTYPE' => 'I']);
        }
        $this->postJson('/api/report/monthly-attendance', ['office_shift_id' => 2])->assertOk()
            ->assertJsonPath('employees.0.late_count', 3)->assertJsonPath('employees.0.late_minutes', 16)
            ->assertJsonPath('employees.0.undertime_minutes', 0)->assertJsonCount(3, 'employees.0.days.21.periods');
    }

    public function test_last_day_overnight_departure_is_included_without_next_month_arrivals(): void
    {
        $this->admin();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-02 10:00:00', 'Asia/Manila'));
        DB::table('office_shift_schedules')->where('office_shift_id', 1)->delete();
        DB::table('office_shift_schedules')->insert(['office_shift_id' => 1, 'sequence' => 1,
            'time_in' => '22:00', 'time_out' => '06:00', 'is_next_day' => true]);
        DB::table('checkinout')->insert([
            ['USERID' => 1, 'CHECKTIME' => '2026-09-30 22:05:00', 'CHECKTYPE' => 'I'],
            ['USERID' => 1, 'CHECKTIME' => '2026-10-01 05:45:00', 'CHECKTYPE' => 'O'],
            ['USERID' => 1, 'CHECKTIME' => '2026-10-01 22:30:00', 'CHECKTYPE' => 'I'],
        ]);
        $this->postJson('/api/report/monthly-attendance', ['month' => '2026-09', 'office_shift_id' => 1])->assertOk()
            ->assertJsonPath('employees.0.late_count', 1)->assertJsonPath('employees.0.undertime_count', 1)
            ->assertJsonPath('employees.0.total_minutes', 20)->assertJsonPath('provisional', false);
    }

    public function test_filed_absence_is_exempt_while_unfiled_absence_is_tardy(): void
    {
        $this->admin();
        DB::table('attendance_absences')->insert([
            ['user_id' => 1, 'absence_date' => '2026-09-21', 'status' => 'filed', 'duration' => 'whole_day'],
            ['user_id' => 1, 'absence_date' => '2026-09-22', 'status' => 'unfiled', 'duration' => 'whole_day'],
            ['user_id' => 1, 'absence_date' => '2026-09-23', 'status' => 'filed', 'duration' => 'morning'],
        ]);

        $response = $this->postJson('/api/report/monthly-attendance', ['office_shift_id' => 1])->assertOk();

        $response->assertJsonPath('employees.0.days.20.status', 'Filed whole day absence')
            ->assertJsonPath('employees.0.days.20.absence.status', 'filed')
            ->assertJsonPath('employees.0.days.20.absence.duration', 'whole_day')
            ->assertJsonPath('employees.0.days.21.status', 'Unfiled whole day absence')
            ->assertJsonPath('employees.0.days.21.absence.status', 'unfiled')
            ->assertJsonPath('employees.0.days.22.status', 'Filed morning absence')
            ->assertJsonPath('employees.0.days.22.periods.0.exempt', true)
            ->assertJsonPath('employees.0.days.22.periods.1.exempt', false)
            ->assertJsonPath('employees.0.late_minutes', 480)
            ->assertJsonPath('employees.0.late_count', 2)
            ->assertJsonPath('employees.0.undertime_minutes', 0);
    }
}
