<?php

namespace App\Http\Controllers;

use App\Models\AttendanceAbsence;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceAbsenceController extends Controller
{
    public function store(Request $request)
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'absence_date' => ['required', 'date_format:Y-m-d'],
            'status' => ['required', Rule::in(['filed', 'unfiled'])],
            'duration' => ['required', Rule::in(['whole_day', 'morning', 'afternoon'])],
            'leave_type' => ['nullable', Rule::in(['cto', 'vacation', 'sick', 'spl', 'without_pay'])],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $absence = AttendanceAbsence::updateOrCreate(
            ['user_id' => $validated['user_id'], 'absence_date' => $validated['absence_date']],
            [
                'status' => $validated['status'],
                'duration' => $validated['duration'],
                'leave_type' => $validated['status'] === 'unfiled' ? 'without_pay' : ($validated['leave_type'] ?? null),
                'remarks' => $validated['remarks'] ?? null,
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ],
        );

        return response()->json(['message' => 'Absence saved.', 'absence' => $absence]);
    }

    public function delete(Request $request)
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'absence_date' => ['required', 'date_format:Y-m-d'],
        ]);

        AttendanceAbsence::where('user_id', $validated['user_id'])
            ->whereDate('absence_date', $validated['absence_date'])
            ->delete();

        return response()->json(['message' => 'Absence cleared.']);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless((int) ($request->user()?->role ?? -1) === 1, 403);
    }
}