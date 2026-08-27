<?php

namespace App\Http\Controllers;

use App\Models\Checkinout;
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
        ]);

        $query = Checkinout::query()->select([
            'id', 'USERID', 'CHECKTIME', 'CHECKTYPE', 'SENSORID', 'sn',
        ])->with([
            'user:id,name',
            'user.profile:id,user_id,display_name,first_name,middle_name,last_name,name_extension',
        ]);

        if (!empty($validated['date_from'])) {
            $query->where('CHECKTIME', '>=', Carbon::parse($validated['date_from'])->startOfDay());
        }
        if (!empty($validated['date_to'])) {
            $query->where('CHECKTIME', '<=', Carbon::parse($validated['date_to'])->endOfDay());
        }
        foreach (['checktype' => 'CHECKTYPE', 'sensorid' => 'SENSORID', 'sn' => 'sn'] as $input => $column) {
            if (isset($validated[$input]) && $validated[$input] !== '') {
                $query->where($column, $validated[$input]);
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
}
