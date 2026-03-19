<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::query()
            ->withCount('users')
            ->orderBy('department_name')
            ->get([
                'id',
                'department_name',
                'dep_long',
                'dep_short',
                'status',
                'user_add',
                'user_last_modify',
                'created_at',
                'updated_at',
            ]);

        return response()->json(compact('departments'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'department_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'department_name')->whereNull('deleted_at'),
            ],
            'dep_long' => [
                'nullable',
                'string',
                'max:255',
            ],
            'dep_short' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('departments', 'dep_short')->whereNull('deleted_at'),
            ],
            'status' => ['nullable', 'boolean'],
        ]);

        $department = Department::create([
            'department_name' => $validated['department_name'],
            'dep_long' => $validated['dep_long'] ?? null,
            'dep_short' => $validated['dep_short'] ?? null,
            'status' => (bool) ($validated['status'] ?? true),
            'user_add' => $request->user()?->id,
            'user_last_modify' => $request->user()?->id,
        ]);

        $department->loadCount('users');

        return response()->json([
            'message' => 'Success',
            'department' => $department,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'department_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'department_name')->ignore($request->id)->whereNull('deleted_at'),
            ],
            'dep_long' => [
                'nullable',
                'string',
                'max:255',
            ],
            'dep_short' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('departments', 'dep_short')->ignore($request->id)->whereNull('deleted_at'),
            ],
            'status' => ['required', 'boolean'],
        ]);

        $department = Department::findOrFail($validated['id']);
        $department->update([
            'department_name' => $validated['department_name'],
            'dep_long' => $validated['dep_long'] ?? null,
            'dep_short' => $validated['dep_short'] ?? null,
            'status' => (bool) $validated['status'],
            'user_last_modify' => $request->user()?->id,
        ]);

        $department->loadCount('users');

        return response()->json([
            'message' => 'Success',
            'department' => $department,
        ]);
    }

    public function delete(Request $request)
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
        ]);

        $department = Department::withCount('users')->findOrFail($validated['id']);

        if ($department->users_count > 0) {
            return response()->json([
                'message' => 'Cannot delete department with assigned users',
            ], 422);
        }

        $department->delete();

        return response()->json([
            'message' => 'Success',
        ]);
    }
}
