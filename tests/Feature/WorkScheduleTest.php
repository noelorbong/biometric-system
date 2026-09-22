<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkScheduleRule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Support\AttendanceDatabaseTestCase;

class WorkScheduleTest extends AttendanceDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs((new User)->forceFill(['id' => 1, 'role' => 1]));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-15 20:00', 'Asia/Manila'));
    }

    private function saveRule(array $overrides = [])
    {
        return $this->postJson('/api/work-schedule/store', array_merge([
            'office_shift_id' => 1, 'kind' => 'permanent', 'effective_from' => '2026-09-01',
            'profile_key' => 'compressed', 'working_days' => [1, 2, 3, 4], 'reason' => 'School work schedule',
        ], $overrides));
    }

    private function daily(string $date)
    {
        return $this->postJson('/api/report/daily-attendance', ['date' => $date, 'office_shift_id' => 1])->assertOk();
    }

    public function test_effective_date_preserves_history_and_four_day_rest_days(): void
    {
        $this->saveRule()->assertOk();
        foreach (['2026-08-31', '2026-09-01', '2026-09-04'] as $date) {
            DB::table('checkinout')->insert(['USERID' => 1, 'CHECKTIME' => $date.' 08:05:00', 'CHECKTYPE' => 'I']);
        }
        $this->daily('2026-08-31')->assertJsonPath('summary.late_minutes', 5);
        $this->daily('2026-09-01')->assertJsonPath('summary.late_minutes', 65)
            ->assertJsonPath('employees.0.periods.1.scheduled_out', '18:00');
        $this->daily('2026-09-04')->assertJsonPath('summary.late_minutes', 0)
            ->assertJsonPath('employees.0.status', 'Rest day')->assertJsonPath('employees.0.missing_count', 0);
        // Editing a current office shift cannot rewrite captured historical or effective profiles.
        DB::table('office_shift_schedules')->where('office_shift_id', 1)->where('sequence', 1)->update(['time_in' => '09:00']);
        $this->daily('2026-08-31')->assertJsonPath('summary.late_minutes', 5);
        $this->daily('2026-09-01')->assertJsonPath('summary.late_minutes', 65);
        $monthly = $this->postJson('/api/report/monthly-attendance', ['month' => '2026-09', 'office_shift_id' => 1])->assertOk();
        $monthly->assertJsonPath('employees.0.days.3.rest_day', true)->assertJsonPath('employees.0.late_minutes', 65);
    }

    public function test_friday_holiday_suggests_review_then_weekly_override_changes_only_that_week(): void
    {
        $this->saveRule(['effective_from' => '2026-08-01'])->assertOk();
        DB::table('holidays')->insert(['holiday_date' => '2026-09-04', 'name' => 'Friday holiday', 'duration' => 'full_day', 'is_working_day' => false]);
        $this->postJson('/api/work-schedules', ['month' => '2026-08'])->assertOk()
            ->assertJsonPath('suggestions.0.week_start', '2026-08-31');
        DB::table('checkinout')->insert(['USERID' => 1, 'CHECKTIME' => '2026-09-01 08:05:00', 'CHECKTYPE' => 'I']);
        $this->daily('2026-09-01')->assertJsonPath('summary.late_minutes', 65);
        $this->saveRule(['kind' => 'weekly', 'effective_from' => '2026-08-31', 'profile_key' => 'standard'])->assertOk();
        $this->daily('2026-09-01')->assertJsonPath('summary.late_minutes', 5)
            ->assertJsonPath('employees.0.periods.1.scheduled_out', '17:00');
        $this->daily('2026-09-07')->assertJsonPath('employees.0.periods.1.scheduled_out', '18:00');
        $this->postJson('/api/work-schedules', ['month' => '2026-08'])->assertOk()->assertJsonCount(0, 'suggestions');
        $this->postJson('/api/user/checkinout', ['user_id' => 1, 'year' => 2026, 'month' => 9])->assertOk()
            ->assertJsonPath('schedule_by_date.2026-09-01.schedules.0.time_in', '08:00')
            ->assertJsonPath('schedule_by_date.2026-09-07.schedules.0.time_in', '07:00');
    }

    public function test_weekday_holidays_and_suspensions_credit_the_applicable_hours(): void
    {
        $this->saveRule()->assertOk();
        DB::table('holidays')->insert(['holiday_date' => '2026-09-02', 'name' => 'Midweek holiday', 'duration' => 'full_day', 'is_working_day' => false]);
        $this->daily('2026-09-02')->assertJsonPath('employees.0.holiday_credit_minutes', 600)
            ->assertJsonPath('employees.0.missing_count', 0);
        $this->postJson('/api/work-suspension/store', ['name' => 'Morning suspension', 'suspension_date' => '2026-09-03',
            'duration' => 'morning', 'office_shift_id' => 1])->assertOk();
        $this->daily('2026-09-03')->assertJsonPath('employees.0.holiday_credit_minutes', 300)
            ->assertJsonPath('employees.0.periods.0.exempt', true)->assertJsonPath('employees.0.periods.1.exempt', false);
        $this->postJson('/api/report/monthly-attendance', ['month' => '2026-09', 'office_shift_id' => 1])->assertOk()
            ->assertJsonPath('employees.0.holiday_credit_minutes', 900);
        $this->postJson('/api/work-suspension/store', ['name' => 'Friday suspension', 'suspension_date' => '2026-09-11', 'duration' => 'full_day'])->assertOk();
        $this->postJson('/api/work-schedules', ['month' => '2026-09'])->assertOk()->assertJsonPath('suggestions.0.week_start', '2026-09-07');
    }

    public function test_employee_weekly_exception_takes_priority_and_can_be_removed(): void
    {
        $this->saveRule()->assertOk();
        $this->saveRule(['kind' => 'weekly', 'effective_from' => '2026-09-07', 'profile_key' => 'standard'])->assertOk();
        $id = $this->saveRule(['kind' => 'weekly', 'effective_from' => '2026-09-07', 'user_id' => 1])->assertOk()->json('rule.id');
        $this->daily('2026-09-08')->assertJsonPath('employees.0.periods.0.scheduled_in', '07:00');
        $this->postJson('/api/work-schedule/delete', ['id' => $id])->assertOk();
        $this->daily('2026-09-08')->assertJsonPath('employees.0.periods.0.scheduled_in', '08:00');
    }

    public function test_permissions_validation_and_baseline_protection(): void
    {
        $this->saveRule(['kind' => 'weekly', 'effective_from' => '2026-09-01'])->assertUnprocessable();
        $this->saveRule(['user_id' => 2])->assertUnprocessable();
        $this->saveRule(['working_days' => []])->assertUnprocessable();
        $this->saveRule()->assertOk();
        $this->saveRule()->assertUnprocessable();
        $this->postJson('/api/work-schedule/delete', ['id' => WorkScheduleRule::where('kind', 'baseline')->value('id')])->assertUnprocessable();
        $this->actingAs((new User)->forceFill(['id' => 2, 'role' => 0]));
        foreach (['work-schedules', 'work-schedule/store', 'work-schedule/delete', 'work-suspension/store', 'work-suspension/delete'] as $route) {
            $this->postJson('/api/'.$route)->assertForbidden();
        }
    }
}
