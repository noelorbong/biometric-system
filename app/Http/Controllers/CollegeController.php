<?php

namespace App\Http\Controllers;

use App\Models\College;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CollegeController extends Controller
{
    public function index()
    {
        $colleges = College::query()
            ->orderBy('college_long')
            ->get([
                'id',
                'company_id',
                'college_short',
                'college_long',
                'college_head',
                'status',
                'user_add',
                'user_last_modify',
                'created_at',
                'updated_at',
            ]);

        return response()->json(compact('colleges'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => ['nullable', 'integer', 'min:1'],
            'college_short' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('colleges', 'college_short')->whereNull('deleted_at'),
            ],
            'college_long' => [
                'required',
                'string',
                'max:50',
                Rule::unique('colleges', 'college_long')->whereNull('deleted_at'),
            ],
            'college_head' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'boolean'],
        ]);

        $college = College::create([
            'company_id' => $validated['company_id'] ?? 1,
            'college_short' => $validated['college_short'] ?? null,
            'college_long' => $validated['college_long'],
            'college_head' => $validated['college_head'] ?? null,
            'status' => (bool) ($validated['status'] ?? true),
            'user_add' => $request->user()?->id,
            'user_last_modify' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Success',
            'college' => $college,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', Rule::exists('colleges', 'id')->whereNull('deleted_at')],
            'company_id' => ['nullable', 'integer', 'min:1'],
            'college_short' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('colleges', 'college_short')->ignore($request->id)->whereNull('deleted_at'),
            ],
            'college_long' => [
                'required',
                'string',
                'max:50',
                Rule::unique('colleges', 'college_long')->ignore($request->id)->whereNull('deleted_at'),
            ],
            'college_head' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'boolean'],
        ]);

        $college = College::findOrFail($validated['id']);
        $college->update([
            'company_id' => $validated['company_id'] ?? $college->company_id ?? 1,
            'college_short' => $validated['college_short'] ?? null,
            'college_long' => $validated['college_long'],
            'college_head' => $validated['college_head'] ?? null,
            'status' => (bool) $validated['status'],
            'user_last_modify' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Success',
            'college' => $college,
        ]);
    }

    public function delete(Request $request)
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', Rule::exists('colleges', 'id')->whereNull('deleted_at')],
        ]);

        $college = College::findOrFail($validated['id']);
        $college->delete();

        return response()->json([
            'message' => 'Success',
        ]);
    }
}
