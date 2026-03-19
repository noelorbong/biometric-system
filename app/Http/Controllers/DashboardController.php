<?php

namespace App\Http\Controllers;

use App\Models\Checkinout;
use App\Models\College;
use App\Models\Department;
use App\Models\Machine;
use App\Models\OfficeShift;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private function roleLabel(int $role): string
    {
        return match ($role) {
            0 => 'User',
            1 => 'Super Admin',
            2 => 'Region Admin',
            3 => 'SUC Admin',
            4 => 'Campus Admin',
            5 => 'College Admin',
            6 => 'Employee',
            default => 'Unknown',
        };
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $now = now();
        $startOfDay = $now->copy()->startOfDay();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfYear = $now->copy()->startOfYear();

        if ((int) ($user?->role ?? -1) === 1) {
            $recentAttendance = Checkinout::query()
                ->with('user:id,name')
                ->orderByDesc('CHECKTIME')
                ->limit(8)
                ->get(['USERID', 'CHECKTIME', 'CHECKTYPE', 'sn'])
                ->map(function (Checkinout $record) {
                    return [
                        'userid' => $record->USERID,
                        'name' => $record->user?->name ?? ('User #' . $record->USERID),
                        'checktime' => optional($record->CHECKTIME)?->toISOString(),
                        'checktype' => $record->CHECKTYPE,
                        'machine_sn' => $record->sn,
                    ];
                })
                ->values();

            $machineOverview = Machine::query()
                ->orderBy('MachineAlias')
                ->limit(6)
                ->get(['ID', 'MachineAlias', 'IP', 'Enabled', 'AutoDownload', 'AutoDownloadLastSyncedAt'])
                ->map(function (Machine $machine) {
                    return [
                        'id' => $machine->ID,
                        'name' => $machine->MachineAlias,
                        'ip' => $machine->IP,
                        'enabled' => (bool) $machine->Enabled,
                        'auto_download' => (bool) $machine->AutoDownload,
                        'last_synced_at' => optional($machine->AutoDownloadLastSyncedAt)?->toISOString(),
                    ];
                })
                ->values();

            return response()->json([
                'role' => 'super_admin',
                'role_label' => $this->roleLabel((int) $user->role),
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'stats' => [
                    'total_users' => User::count(),
                    'active_users' => User::where('status', true)->count(),
                    'total_machines' => Machine::count(),
                    'auto_download_machines' => Machine::where('AutoDownload', true)->count(),
                    'total_departments' => Department::count(),
                    'total_colleges' => College::count(),
                    'total_office_shifts' => OfficeShift::count(),
                    'attendance_today' => Checkinout::where('CHECKTIME', '>=', $startOfDay)->count(),
                    'attendance_this_month' => Checkinout::where('CHECKTIME', '>=', $startOfMonth)->count(),
                ],
                'recent_attendance' => $recentAttendance,
                'machine_overview' => $machineOverview,
            ]);
        }

        $user->loadMissing([
            'departmentRef:id,department_name,dep_short',
            'collegeRef:id,college_short,college_long',
            'officeShift:id,name,schedule,is_flexible',
            'officeShift.schedules:id,office_shift_id,sequence,time_in,time_out,is_next_day',
        ]);

        $userAttendance = Checkinout::query()->where('USERID', $user->id);

        $recentAttendance = (clone $userAttendance)
            ->orderByDesc('CHECKTIME')
            ->limit(8)
            ->get(['USERID', 'CHECKTIME', 'CHECKTYPE', 'sn'])
            ->map(function (Checkinout $record) {
                return [
                    'userid' => $record->USERID,
                    'name' => null,
                    'checktime' => optional($record->CHECKTIME)?->toISOString(),
                    'checktype' => $record->CHECKTYPE,
                    'machine_sn' => $record->sn,
                ];
            })
            ->values();

        return response()->json([
            'role' => 'user',
            'role_label' => $this->roleLabel((int) $user->role),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'department' => $user->departmentRef?->department_name,
                'college' => $user->collegeRef?->college_long ?? $user->collegeRef?->college_short,
                'office_shift' => $user->officeShift?->name,
            ],
            'stats' => [
                'attendance_today' => (clone $userAttendance)->where('CHECKTIME', '>=', $startOfDay)->count(),
                'attendance_this_month' => (clone $userAttendance)->where('CHECKTIME', '>=', $startOfMonth)->count(),
                'attendance_this_year' => (clone $userAttendance)->where('CHECKTIME', '>=', $startOfYear)->count(),
                'last_punch_at' => optional((clone $userAttendance)->orderByDesc('CHECKTIME')->first(['CHECKTIME'])?->CHECKTIME)?->toISOString(),
            ],
            'schedule' => [
                'name' => $user->officeShift?->name,
                'is_flexible' => (bool) ($user->officeShift?->is_flexible ?? false),
                'schedule_label' => $user->officeShift?->schedule,
                'entries' => $user->officeShift?->schedules?->map(function ($entry) {
                    return [
                        'sequence' => $entry->sequence,
                        'time_in' => $entry->time_in,
                        'time_out' => $entry->time_out,
                        'is_next_day' => (bool) $entry->is_next_day,
                    ];
                })->values() ?? [],
            ],
            'recent_attendance' => $recentAttendance,
        ]);
    }

}
