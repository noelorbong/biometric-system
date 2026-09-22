<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\OfficeShift;
use App\Models\User;
use App\Services\DailyAttendanceService;
use App\Services\AttendanceLogService;
use App\Services\WorkScheduleResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class DailyAttendanceController extends Controller
{
    public function index(Request $request, DailyAttendanceService $attendance, AttendanceLogService $logs, WorkScheduleResolver $resolver)
    {
        abort_unless((int) ($request->user()?->role ?? -1) === 1, 403);
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'office_shift_id' => ['nullable', 'integer', 'exists:office_shifts,id,deleted_at,NULL'],
        ]);
        $now = CarbonImmutable::now(config('app.timezone'));
        $date = $validated['date'] ?? $now->toDateString();
        $start = CarbonImmutable::parse($date, $now->timezone)->startOfDay();
        $end = $start->addDays(2);
        $resolver->load($start, $end);
        $shifts = OfficeShift::with('schedules')->orderBy('name')->get();
        $users = User::with(['profile', 'officeShift.schedules'])->where('status', true)
            ->when($validated['office_shift_id'] ?? null, fn ($query, $id) => $query->where('office_shift_id', $id))
            ->orderBy('name')->get();
        $ids = $users->modelKeys();
        $punches = $logs->forUsers($ids, $start, $end);
        $holidays = $resolver->events($date, $validated['office_shift_id'] ?? null);
        $employees = $users->map(function ($user) use ($attendance, $date, $punches, $holidays, $now, $resolver) {
            $resolved = $resolver->resolve($user, $date);
            $profile = $user->profile;
            $fullName = trim(implode(' ', array_filter([$profile?->first_name, $profile?->middle_name,
                $profile?->last_name, $profile?->name_extension])));
            return array_merge([
                'id' => $user->id, 'name' => $profile?->display_name ?: ($fullName ?: $user->name),
                'shift_id' => $user->office_shift_id, 'shift_name' => $user->officeShift?->name ?? 'Unassigned',
            ], $attendance->calculate($date, $resolved, $punches[$user->id] ?? [], $resolved['_holidays'] ?? $holidays, $now));
        })->sortBy([['late_minutes', 'desc'], ['name', 'asc']])->values();

        return response()->json([
            'date' => $date, 'today' => $now->toDateString(), 'timezone' => $now->timezoneName,
            'generated_at' => $now->format('Y-m-d H:i:s'),
            'office_shifts' => $shifts, 'holidays' => $holidays, 'employees' => $employees,
            'summary' => [
                'late_count' => $employees->where('late_minutes', '>', 0)->count(),
                'late_minutes' => $employees->sum('late_minutes'),
                'undertime_count' => $employees->where('undertime_minutes', '>', 0)->count(),
                'undertime_minutes' => $employees->sum('undertime_minutes'),
            ],
        ]);
    }
}
