<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HolidayController extends Controller
{
    public function index()
    {
        $holidays = Holiday::query()
            ->orderBy('holiday_date')
            ->get();

        return response()->json(compact('holidays'));
    }

    public function store(Request $request)
    {
        $validated = $this->validatedHoliday($request);

        $holiday = Holiday::create([
            ...$validated,
            'is_working_day' => (bool) ($validated['is_working_day'] ?? false),
            'notes' => $validated['notes'] ?? null,
            'user_add' => $request->user()?->id,
            'user_last_modify' => $request->user()?->id,
        ]);

        return response()->json(['message' => 'Success', 'holiday' => $holiday]);
    }

    public function update(Request $request)
    {
        $validated = $this->validatedHoliday($request, true);
        $holiday = Holiday::findOrFail($validated['id']);

        $holiday->update([
            ...collect($validated)->except('id')->all(),
            'is_working_day' => (bool) ($validated['is_working_day'] ?? false),
            'notes' => $validated['notes'] ?? null,
            'user_last_modify' => $request->user()?->id,
        ]);

        return response()->json(['message' => 'Success', 'holiday' => $holiday]);
    }

    public function delete(Request $request)
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', Rule::exists('holidays', 'id')->whereNull('deleted_at')],
        ]);

        Holiday::findOrFail($validated['id'])->delete();

        return response()->json(['message' => 'Success']);
    }

    private function validatedHoliday(Request $request, bool $isUpdate = false): array
    {
        $dateRule = Rule::unique('holidays', 'holiday_date')->whereNull('deleted_at');
        if ($isUpdate) {
            $dateRule->ignore($request->id);
        }

        return $request->validate([
            'id' => [$isUpdate ? 'required' : 'nullable', 'integer'],
            'name' => ['required', 'string', 'max:120'],
            'holiday_date' => ['required', 'date', $dateRule],
            'type' => ['required', Rule::in(['regular', 'special_non_working', 'special_working'])],
            'duration' => ['required', Rule::in(['full_day', 'morning', 'afternoon'])],
            'is_working_day' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}