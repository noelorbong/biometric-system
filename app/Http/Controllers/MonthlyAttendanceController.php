<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\AttendanceAbsence;
use App\Models\OfficeShift;
use App\Models\User;
use App\Services\AttendanceLogService;
use App\Services\MonthlyAttendanceService;
use App\Services\WorkScheduleResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Services\MonthlyReportPage;

class MonthlyAttendanceController extends Controller
{
    public function index(Request $request, MonthlyAttendanceService $attendance, AttendanceLogService $logs, WorkScheduleResolver $resolver)
    {
        $started = microtime(true);
        abort_unless((int) ($request->user()?->role ?? -1) === 1, 403);
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'issues_only' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:late_count,undertime_count,total_minutes,name'],
            'refresh' => ['nullable', 'boolean'],
            'export_all' => ['nullable', 'boolean'],
            'office_shift_id' => ['nullable', 'integer', 'exists:office_shifts,id,deleted_at,NULL'],
        ]);
        $paginated = isset($validated['per_page']);
        $useSnapshot = $paginated || ($validated['export_all'] ?? false);
        $cache = Cache::store(app()->environment('testing') ? 'array' : 'file');
        $key = 'monthly-report:v1:'.hash('sha256', json_encode([
            $validated['month'] ?? now()->format('Y-m'), $validated['office_shift_id'] ?? null, config('app.timezone'),
        ]));
        if ($useSnapshot && ($validated['refresh'] ?? false)) {
            $cache->forget($key);
        }
        $build = fn () => $this->buildReport($validated, $attendance, $logs, $resolver);
        // Paging and exports reuse a short-lived calculation, avoiding a full recalculation per page.
        $data = $useSnapshot ? $cache->remember($key, 30, $build) : $build();
        $data = app(MonthlyReportPage::class)->apply($data, $validated);
        return response()->json($data)->header('Server-Timing', sprintf('total;dur=%.2f', (microtime(true) - $started) * 1000));
    }

    private function buildReport(array $validated, MonthlyAttendanceService $attendance, AttendanceLogService $logs, WorkScheduleResolver $resolver): array
    {
        $now = CarbonImmutable::now(config('app.timezone'));
        $month = CarbonImmutable::parse(($validated['month'] ?? $now->format('Y-m')).'-01', $now->timezone)->startOfDay();
        $end = $month->addMonth();
        $resolver->load($month, $end->addDay());
        $shifts = OfficeShift::with('schedules')->orderBy('name')->get();
        $users = User::with(['profile', 'officeShift.schedules'])->where('status', true)
            ->when($validated['office_shift_id'] ?? null, fn ($query, $id) => $query->where('office_shift_id', $id))
            ->orderBy('name')->get();
        // One range query includes the next month's first day for overnight departures.
        $punches = $logs->forUsers($users->modelKeys(), $month, $end->addDay());
        $holidays = Holiday::where('holiday_date', '>=', $month->toDateString())
            ->where('holiday_date', '<', $end->toDateString())->get();
        $holidaysByDate = $holidays->groupBy(fn ($holiday) => $holiday->holiday_date->format('Y-m-d'))
            ->map(fn ($rows) => $rows->toArray())->all();
        $absencesByUser = AttendanceAbsence::whereIn('user_id', $users->modelKeys())
            ->where('absence_date', '>=', $month->toDateString())
            ->where('absence_date', '<', $end->toDateString())
            ->get()
            ->groupBy('user_id')
            ->map(fn ($records) => $records->keyBy(fn ($absence) => $absence->absence_date->format('Y-m-d'))
                ->map(fn ($absence) => [
                    'status' => $absence->status,
                    'duration' => $absence->duration,
                    'leave_type' => $absence->leave_type,
                    'remarks' => $absence->remarks,
                ])->all())
            ->all();
        $days = [];
        for ($day = 1; $day <= $month->daysInMonth; $day++) {
            $date = $month->day($day);
            $days[] = ['date' => $date->toDateString(), 'day' => $day, 'weekday' => $date->format('D'),
                'weekend' => $date->isWeekend(), 'future' => $date->startOfDay() > $now,
                'holidays' => $resolver->events($date->toDateString(), $validated['office_shift_id'] ?? null)];
        }
        $employees = $users->map(function ($user) use ($attendance, $month, $punches, $holidaysByDate, $absencesByUser, $now, $resolver) {
            $profile = $user->profile;
            $fullName = trim(implode(' ', array_filter([$profile?->first_name, $profile?->middle_name,
                $profile?->last_name, $profile?->name_extension])));
            return array_merge([
                'id' => $user->id, 'name' => $profile?->display_name ?: ($fullName ?: $user->name),
                'shift_id' => $user->office_shift_id, 'shift_name' => $user->officeShift?->name ?? 'Unassigned',
            ], $attendance->calculate($month, $user->officeShift?->toArray(), $punches[$user->id] ?? [], $holidaysByDate, $absencesByUser[$user->id] ?? [], $now, $resolver->forMonth($user, $month)));
        })->sortBy([['late_count', 'desc'], ['total_minutes', 'desc'], ['name', 'asc']])->values();

        $data = [
            'month' => $month->format('Y-m'), 'month_label' => $month->format('F Y'),
            'generated_at' => $now->format('Y-m-d H:i:s'), 'timezone' => $now->timezoneName,
            'provisional' => $end->addDay() > $now,
            'days' => $days, 'office_shifts' => $shifts, 'employees' => $employees,
        ];
        $data['employees'] = $employees->all();
        return $data;
    }
}
