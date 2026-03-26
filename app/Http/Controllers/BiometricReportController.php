<?php

namespace App\Http\Controllers;

use App\Models\Checkinout;
use App\Models\User;
use Illuminate\Http\Request;

class BiometricReportController extends Controller
{
    public function generate(Request $request)
    {
        if ((int) ($request->user()?->role ?? -1) !== 1) {
            return response()->json([
                'message' => 'Forbidden',
            ], 403);
        }

        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:1970', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'office_shift_id' => ['nullable', 'integer', 'exists:office_shifts,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'college_id' => ['nullable', 'integer', 'exists:colleges,id'],
        ]);

        $year = (int) $validated['year'];
        $month = (int) $validated['month'];
        $daysInMonth = date('t', mktime(0, 0, 0, $month, 1, $year));
        // $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        $usersQuery = User::query()
            ->with([
                'profile:id,user_id,first_name,middle_name,last_name,name_extension',
                'officeShift:id,name,schedule',
                'departmentRef:id,department_name',
                'collegeRef:id,college_short,college_long',
            ])
            ->where('status', true);

        if (array_key_exists('office_shift_id', $validated) && $validated['office_shift_id']) {
            $usersQuery->where('office_shift_id', $validated['office_shift_id']);
        }

        if (array_key_exists('department_id', $validated) && $validated['department_id']) {
            $usersQuery->where('department_id', $validated['department_id']);
        }

        if (array_key_exists('college_id', $validated) && $validated['college_id']) {
            $usersQuery->where('college_id', $validated['college_id']);
        }

        $users = $usersQuery
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'office_shift_id', 'department_id', 'college_id', 'department']);

        if ($users->isEmpty()) {
            return response()->json([
                'report_users' => [],
                'year' => $year,
                'month' => $month,
            ]);
        }

        $userIds = $users->pluck('id')->all();

        $checkinouts = Checkinout::query()
            ->whereIn('USERID', $userIds)
            ->whereYear('CHECKTIME', $year)
            ->whereMonth('CHECKTIME', $month)
            ->orderBy('CHECKTIME')
            ->get(['USERID', 'CHECKTIME', 'CHECKTYPE']);

        $recordsByUserDate = [];

        foreach ($checkinouts as $row) {
            $dateKey = date('Y-m-d', strtotime((string) $row->CHECKTIME));
            $recordsByUserDate[$row->USERID][$dateKey][] = [
                'checktime' => $row->CHECKTIME,
                'checktype' => strtoupper((string) $row->CHECKTYPE),
            ];
        }

        $toTimeLabel = function ($value) {
            if (!$value) {
                return '';
            }

            return date('h:i A', strtotime((string) $value));
        };

        $buildDayRecord = function (array $punches) use ($toTimeLabel) {
            usort($punches, fn ($a, $b) => strtotime((string) $a['checktime']) <=> strtotime((string) $b['checktime']));

            $normalized = [];

            foreach ($punches as $item) {
                $type = $item['checktype'] ?? '';
                if (!in_array($type, ['I', 'O'], true)) {
                    continue;
                }

                $last = $normalized[count($normalized) - 1] ?? null;
                if (!$last || $last['type'] !== $type) {
                    $normalized[] = ['type' => $type, 'time' => $item['checktime']];
                    continue;
                }

                if ($type === 'O') {
                    $normalized[count($normalized) - 1]['time'] = $item['checktime'];
                }
            }

            $sessions = [];
            $current = null;

            foreach ($normalized as $punch) {
                if ($punch['type'] === 'I') {
                    if (!$current || ($current['check_in'] && $current['check_out'])) {
                        $current = ['check_in' => $punch['time'], 'check_out' => null];
                    } elseif ($current['check_in'] && !$current['check_out']) {
                        $sessions[] = $current;
                        $current = ['check_in' => $punch['time'], 'check_out' => null];
                    } else {
                        $current = ['check_in' => $punch['time'], 'check_out' => null];
                    }

                    continue;
                }

                if (!$current) {
                    $current = ['check_in' => null, 'check_out' => $punch['time']];
                    continue;
                }

                if ($current['check_in'] && !$current['check_out']) {
                    $current['check_out'] = $punch['time'];
                    $sessions[] = $current;
                    $current = null;
                }
            }

            if ($current && ($current['check_in'] || $current['check_out'])) {
                $sessions[] = $current;
            }

            $am = $sessions[0] ?? ['check_in' => null, 'check_out' => null];
            $pm = $sessions[1] ?? ['check_in' => null, 'check_out' => null];

            return [
                'am_in' => $toTimeLabel($am['check_in'] ?? null),
                'am_out' => $toTimeLabel($am['check_out'] ?? null),
                'pm_in' => $toTimeLabel($pm['check_in'] ?? null),
                'pm_out' => $toTimeLabel($pm['check_out'] ?? null),
                'undertimeHrs' => '',
                'undertimeMin' => '',
            ];
        };

        $reportUsers = $users->map(function ($user) use ($year, $month, $daysInMonth, $recordsByUserDate, $buildDayRecord) {
            $attendanceRecords = [];

            for ($day = 1; $day <= $daysInMonth; $day += 1) {
                $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $punches = $recordsByUserDate[$user->id][$date] ?? [];
                $dayRecord = $buildDayRecord($punches);
                $attendanceRecords[] = array_merge(['date' => $date], $dayRecord);
            }

            $profile = $user->profile;
            $fullName = trim(implode(' ', array_filter([
                $profile?->first_name,
                $profile?->middle_name,
                $profile?->last_name,
                $profile?->name_extension,
            ])));

            return [
                'id' => $user->id,
                'name' => $fullName ?: $user->name,
                'email' => $user->email,
                'office_shift' => $user->officeShift,
                'department' => $user->departmentRef?->department_name ?? $user->department,
                'college' => $user->collegeRef?->college_long ?? $user->collegeRef?->college_short,
                'attendance_records' => $attendanceRecords,
            ];
        })->values();

        return response()->json([
            'report_users' => $reportUsers,
            'year' => $year,
            'month' => $month,
        ]);
    }
}
