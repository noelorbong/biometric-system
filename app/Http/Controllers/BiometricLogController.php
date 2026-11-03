<?php

namespace App\Http\Controllers;

use App\Models\Checkinout;
use App\Models\BiometricLogOverride;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;

class BiometricLogController extends Controller
{
    public function index(Request $request)
    {
        if ((int) ($request->user()?->role ?? -1) !== 1) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'checktype' => ['nullable', 'string', 'max:10'],
            'sensorid' => ['nullable', 'string', 'max:100'],
            'sn' => ['nullable', 'string', 'max:100'],
            'sort_by' => ['nullable', 'in:id,CHECKTIME'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'cursor' => ['nullable', 'string'],
            'tab' => ['nullable', 'in:logs,overrides'],
            'override_view' => ['nullable', 'in:corrections,deleted'],
        ]);

        if (($validated['tab'] ?? 'logs') === 'overrides' && ($validated['override_view'] ?? 'deleted') === 'corrections') {
            return $this->corrections($validated);
        }

        $query = Checkinout::query()->select([
            'id', 'USERID', 'CHECKTIME', 'CHECKTYPE', 'SENSORID', 'sn',
        ])->with([
            'user:id,name',
            'user.profile:id,user_id,display_name,first_name,middle_name,last_name,name_extension',
        ]);

        if (($validated['tab'] ?? 'logs') === 'overrides') {
            $query->onlyTrashed();
        }

        if (!empty($validated['date_from'])) {
            $query->where('CHECKTIME', '>=', Carbon::parse($validated['date_from'])->startOfDay());
        }
        if (!empty($validated['date_to'])) {
            $query->where('CHECKTIME', '<=', Carbon::parse($validated['date_to'])->endOfDay());
        }
        foreach (['checktype' => 'CHECKTYPE', 'sensorid' => 'SENSORID', 'sn' => 'sn'] as $input => $column) {
            if (isset($validated[$input]) && $validated[$input] !== '') {
                $value = $validated[$input];
                $upper = strtoupper(trim($value));

                if ($input === 'checktype' && in_array($upper, Checkinout::OUT_VARIANTS, true)) {
                    $query->whereIn($column, Checkinout::OUT_VARIANTS);
                } elseif ($input === 'checktype' && in_array($upper, Checkinout::IN_VARIANTS, true)) {
                    $query->whereIn($column, Checkinout::IN_VARIANTS);
                } else {
                    $query->where($column, $value);
                }
            }
        }

        $sortBy = $validated['sort_by'] ?? 'id';
        $direction = $validated['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $direction);
        if ($sortBy !== 'id') {
            $query->orderBy('id', $direction);
        }

        $cursor = isset($validated['cursor']) ? Cursor::fromEncoded($validated['cursor']) : null;
        $logs = $query->cursorPaginate($validated['per_page'] ?? 25, ['*'], 'cursor', $cursor);
        $nextCursor = $logs->nextCursor()?->encode();
        $previousCursor = $logs->previousCursor()?->encode();
        $data = $logs->getCollection()
            ->map(function (Checkinout $log) {
                $profile = $log->user?->profile;
                $userName = $profile?->display_name ?: $profile?->full_name ?: $log->user?->name;

                return [
                    'id' => $log->getKey(),
                    'user' => $userName ?: 'Unknown user',
                    'user_id' => $log->USERID,
                    'checktime' => optional($log->CHECKTIME)->format('Y-m-d H:i:s'),
                    'checktype' => $log->CHECKTYPE,
                    'sensorid' => $log->SENSORID,
                    'sn' => $log->sn,
                ];
            })
            ->values();

        return response()->json([
            'logs' => [
                'data' => $data,
                'per_page' => $logs->perPage(),
                'next_cursor' => $nextCursor,
                'prev_cursor' => $previousCursor,
            ],
        ]);
    }

    public function delete(Request $request)
    {
        abort_unless((int) ($request->user()?->role ?? -1) === 1, 403);
        $validated = $request->validate(['id' => ['required', 'integer']]);
        $log = Checkinout::withTrashed()->findOrFail($validated['id']);
        if (!$log->trashed()) $log->delete();
        return response()->json(['message' => 'Log deleted. You can restore it from Overrides.']);
    }

    public function deletedForDay(Request $request)
    {
        abort_unless((int) ($request->user()?->role ?? -1) === 1, 403);
        $data = $request->validate(['user_id' => ['required', 'integer'], 'date' => ['required', 'date_format:Y-m-d']]);
        $date = Carbon::parse($data['date']);
        $logs = Checkinout::onlyTrashed()->where('USERID', $data['user_id'])
            ->where('CHECKTIME', '>=', $date->copy()->startOfDay())
            ->where('CHECKTIME', '<', $date->copy()->addDay()->startOfDay())->orderBy('CHECKTIME')->get();
        return response()->json(['logs' => $logs->map(fn ($log) => [
            'id' => $log->id, 'USERID' => $log->USERID,
            'CHECKTIME' => $log->CHECKTIME->format('Y-m-d H:i:s'), 'CHECKTYPE' => $log->CHECKTYPE,
            '_deleted' => true,
        ])]);
    }

    private function corrections(array $filters)
    {
        $query = BiometricLogOverride::withTrashed()->with([
            'user.profile', 'createdBy:id,name', 'updatedBy:id,name',
            'checkinout' => fn ($query) => $query->withTrashed(),
        ]);
        if (!empty($filters['date_from'])) $query->where('new_checktime', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        if (!empty($filters['date_to'])) $query->where('new_checktime', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        if (!empty($filters['checktype'])) {
            $type = strtoupper(trim($filters['checktype']));
            $variants = in_array($type, Checkinout::OUT_VARIANTS, true) ? Checkinout::OUT_VARIANTS
                : (in_array($type, Checkinout::IN_VARIANTS, true) ? Checkinout::IN_VARIANTS : [$type]);
            $query->whereIn('new_checktype', $variants);
        }
        foreach (['sensorid' => 'SENSORID', 'sn' => 'sn'] as $input => $column) {
            if (isset($filters[$input]) && $filters[$input] !== '') {
                $query->whereHas('checkinout', fn ($query) => $query->withTrashed()->where($column, $filters[$input]));
            }
        }
        $sort = ($filters['sort_by'] ?? 'id') === 'CHECKTIME' ? 'new_checktime' : 'id';
        $direction = $filters['sort_direction'] ?? 'desc';
        $query->orderBy($sort, $direction);
        if ($sort !== 'id') $query->orderBy('id', $direction);
        $cursor = isset($filters['cursor']) ? Cursor::fromEncoded($filters['cursor']) : null;
        $records = $query->cursorPaginate($filters['per_page'] ?? 25, ['*'], 'cursor', $cursor);
        return response()->json(['logs' => [
            'data' => $records->getCollection()->map(fn ($record) => [
                'id' => $record->id, 'user_id' => $record->user_id,
                'user' => $record->user?->profile?->display_name ?: $record->user?->profile?->full_name ?: $record->user?->name ?: 'Unknown user',
                'checktime' => $record->new_checktime?->format('Y-m-d H:i:s'), 'checktype' => $record->new_checktype,
                'old_checktime' => $record->old_checktime?->format('Y-m-d H:i:s'), 'old_checktype' => $record->old_checktype,
                'action_type' => $record->action_type,
                'status' => $record->trashed() ? 'Removed' : ($record->checkinout?->trashed() ? 'Original deleted' : 'Saved'),
                'created_by' => $record->createdBy?->name, 'updated_by' => $record->updatedBy?->name,
                'updated_at' => $record->updated_at?->format('Y-m-d H:i:s'),
                'sensorid' => $record->checkinout?->SENSORID, 'sn' => $record->checkinout?->sn,
            ])->values(),
            'per_page' => $records->perPage(), 'next_cursor' => $records->nextCursor()?->encode(), 'prev_cursor' => $records->previousCursor()?->encode(),
        ]]);
    }

    public function restore(Request $request)
    {
        abort_unless((int) ($request->user()?->role ?? -1) === 1, 403);
        $validated = $request->validate(['id' => ['required', 'integer']]);
        $log = Checkinout::withTrashed()->findOrFail($validated['id']);
        if ($log->trashed()) $log->restore();
        return response()->json(['message' => 'Log restored.']);
    }
}
