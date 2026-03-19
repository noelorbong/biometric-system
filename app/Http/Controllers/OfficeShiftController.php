<?php

namespace App\Http\Controllers;

use App\Models\OfficeShift;
use App\Models\OfficeShiftSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OfficeShiftController extends Controller
{
    private function formatTimeRange(string $timeIn, string $timeOut, bool $isNextDay): string
    {
        $in = date('g:iA', strtotime($timeIn));
        $out = date('g:iA', strtotime($timeOut));

        return $isNextDay ? "{$in}-{$out} (+1 day)" : "{$in}-{$out}";
    }

    private function syncSchedules(OfficeShift $officeShift, array $schedules): void
    {
        // Hard delete first to avoid unique(sequence) collisions with soft-deleted rows.
        OfficeShiftSchedule::where('office_shift_id', $officeShift->id)->forceDelete();

        foreach ($schedules as $index => $row) {
            OfficeShiftSchedule::create([
                'office_shift_id' => $officeShift->id,
                'sequence' => $index + 1,
                'time_in' => $row['time_in'],
                'time_out' => $row['time_out'],
                'is_next_day' => (bool) ($row['is_next_day'] ?? false),
            ]);
        }
    }

    private function buildScheduleSummary(array $schedules, bool $isFlexible): ?string
    {
        if ($isFlexible) {
            return 'Flexible Time';
        }

        if (empty($schedules)) {
            return null;
        }

        return collect($schedules)
            ->map(fn ($row) => $this->formatTimeRange($row['time_in'], $row['time_out'], (bool) ($row['is_next_day'] ?? false)))
            ->implode(', ');
    }

    public function index()
    {
        $office_shifts = OfficeShift::query()
            ->with(['schedules:id,office_shift_id,sequence,time_in,time_out,is_next_day'])
            ->withCount('users')
            ->orderBy('name')
            ->get(['id', 'name', 'schedule', 'is_flexible'])
            ->map(function ($shift) {
                if ($shift->schedules->isNotEmpty()) {
                    $shift->schedule = $shift->schedules
                        ->map(fn ($row) => $this->formatTimeRange($row->time_in, $row->time_out, (bool) $row->is_next_day))
                        ->implode(', ');
                }

                return $shift;
            })
            ->values();

        return response()->json(compact('office_shifts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:office_shifts,name'],
            'schedule' => ['nullable', 'string', 'max:255'],
            'is_flexible' => ['nullable', 'boolean'],
            'schedules' => ['nullable', 'array'],
            'schedules.*.time_in' => ['required_with:schedules', 'date_format:H:i'],
            'schedules.*.time_out' => ['required_with:schedules', 'date_format:H:i'],
            'schedules.*.is_next_day' => ['nullable', 'boolean'],
        ]);

        $isFlexible = (bool) ($validated['is_flexible'] ?? false);
        $schedules = $validated['schedules'] ?? [];

        if (!$isFlexible && empty($schedules)) {
            return response()->json([
                'message' => 'At least one schedule row is required for fixed shifts',
            ], 422);
        }

        $office_shift = DB::transaction(function () use ($validated, $isFlexible, $schedules) {
            $officeShift = OfficeShift::create([
                'name' => $validated['name'],
                'schedule' => $this->buildScheduleSummary($schedules, $isFlexible) ?? ($validated['schedule'] ?? null),
                'is_flexible' => $isFlexible,
            ]);

            if (!$isFlexible && !empty($schedules)) {
                $this->syncSchedules($officeShift, $schedules);
            }

            return $officeShift;
        });

        $office_shift->load(['schedules:id,office_shift_id,sequence,time_in,time_out,is_next_day']);

        return response()->json([
            'message' => 'Success',
            'office_shift' => $office_shift,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:office_shifts,id'],
            'name' => ['required', 'string', 'max:100', Rule::unique('office_shifts', 'name')->ignore($request->id)],
            'schedule' => ['nullable', 'string', 'max:255'],
            'is_flexible' => ['nullable', 'boolean'],
            'schedules' => ['nullable', 'array'],
            'schedules.*.time_in' => ['required_with:schedules', 'date_format:H:i'],
            'schedules.*.time_out' => ['required_with:schedules', 'date_format:H:i'],
            'schedules.*.is_next_day' => ['nullable', 'boolean'],
        ]);

        $isFlexible = (bool) ($validated['is_flexible'] ?? false);
        $schedules = $validated['schedules'] ?? [];

        if (!$isFlexible && empty($schedules)) {
            return response()->json([
                'message' => 'At least one schedule row is required for fixed shifts',
            ], 422);
        }

        $office_shift = DB::transaction(function () use ($validated, $isFlexible, $schedules) {
            $officeShift = OfficeShift::findOrFail($validated['id']);
            $officeShift->update([
                'name' => $validated['name'],
                'schedule' => $this->buildScheduleSummary($schedules, $isFlexible) ?? ($validated['schedule'] ?? null),
                'is_flexible' => $isFlexible,
            ]);

            if ($isFlexible) {
                OfficeShiftSchedule::where('office_shift_id', $officeShift->id)->forceDelete();
            } else {
                $this->syncSchedules($officeShift, $schedules);
            }

            return $officeShift;
        });

        $office_shift->load(['schedules:id,office_shift_id,sequence,time_in,time_out,is_next_day']);

        return response()->json([
            'message' => 'Success',
            'office_shift' => $office_shift,
        ]);
    }

    public function delete(Request $request)
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:office_shifts,id'],
        ]);

        $office_shift = OfficeShift::withCount('users')->findOrFail($validated['id']);

        if ($office_shift->users_count > 0) {
            return response()->json([
                'message' => 'Cannot delete shift with assigned users',
            ], 422);
        }

        $office_shift->delete();

        return response()->json([
            'message' => 'Success',
        ]);
    }
}
