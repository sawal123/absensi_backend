<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\LeaveBalanceResource;
use App\Http\Resources\WorkShiftResource;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Support\LeaveBalances;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $today = Carbon::today();

        $todayAttendance = Attendance::query()
            ->with('user')
            ->where('user_id', $actor->id)
            ->whereDate('date', $today)
            ->first();

        LeaveBalances::ensure($actor->id, $today->year);
        $balances = $actor->leaveBalances()
            ->with('leaveType')
            ->where('year', $today->year)
            ->get();

        $nextAssignment = ShiftAssignment::query()
            ->with('shift')
            ->where('employee_id', $actor->id)
            ->whereDate('date', '>', $today)
            ->orderBy('date')
            ->first();

        $payload = [
            'date' => $today->toDateString(),
            'user' => $actor->only([
                'id',
                'name',
                'email',
                'role',
                'nip',
                'position',
                'team',
                'avatar_url',
            ]),
            'today_attendance' => $todayAttendance
                ? (new AttendanceResource($todayAttendance))->resolve($request)
                : null,
            'leave_balances' => LeaveBalanceResource::collection($balances)->resolve($request),
            'next_shift' => $nextAssignment
                ? [
                    'date' => $nextAssignment->date->toDateString(),
                    'shift' => (new WorkShiftResource($nextAssignment->shift))->resolve($request),
                ]
                : null,
            'pending' => [
                'leave_requests' => LeaveRequest::query()
                    ->where('employee_id', $actor->id)
                    ->where('status', LeaveRequest::STATUS_PENDING)
                    ->count(),
            ],
        ];

        if ($actor->isManager()) {
            $employeeCount = User::query()->employees()->active()->count();
            $pendingLeave = LeaveRequest::query()
                ->where('status', LeaveRequest::STATUS_PENDING)
                ->count();
            $pendingCorrections = \App\Models\AttendanceCorrection::query()
                ->where('status', LeaveRequest::STATUS_PENDING)
                ->count();

            $records = Attendance::query()->whereDate('date', $today)->get();

            $payload['team'] = [
                'total_employees' => $employeeCount,
                'present_today' => $records->whereNotNull('check_in_at')->count(),
                'completed_today' => $records->whereNotNull('check_out_at')->count(),
                'late_today' => $records->where('status', 'late')->count(),
                'pending_leave_requests' => $pendingLeave,
                'pending_corrections' => $pendingCorrections,
            ];
        }

        return response()->json(['data' => $payload]);
    }
}
