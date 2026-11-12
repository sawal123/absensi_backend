<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesRoles;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewRequest;
use App\Http\Requests\StoreLeaveRequestRequest;
use App\Http\Resources\LeaveRequestResource;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Support\CodeGenerator;
use App\Support\DateHelper;
use App\Support\LeaveBalances;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveRequestController extends Controller
{
    use AuthorizesRoles;

    public function index(Request $request): AnonymousResourceCollection
    {
        $actor = $request->user();
        $scope = $request->query('scope', 'mine');

        $query = LeaveRequest::query()
            ->with(['employee', 'leaveType', 'reviewer'])
            ->orderByDesc('created_at');

        if ($scope === 'team' && $actor->isManager()) {
            $query->whereIn(
                'employee_id',
                User::query()->employees()->select('id'),
            );
        } else {
            $query->where('employee_id', $actor->id);
        }

        if ($request->filled('employee_id') && $actor->isManager()) {
            $query->where('employee_id', (int) $request->query('employee_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        if ($request->filled('year')) {
            $query->whereYear('start_date', (int) $request->query('year'));
        }

        return LeaveRequestResource::collection($query->get());
    }

    public function store(StoreLeaveRequestRequest $request): JsonResponse
    {
        $employee = $request->user();
        $data = $request->validated();

        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();
        $holidays = DateHelper::holidayDates($employee->office_location_id, $start, $end);
        $workingDays = DateHelper::workingDays($data['start_date'], $data['end_date'], $holidays);

        if ($workingDays <= 0) {
            throw ValidationException::withMessages([
                'start_date' => 'Rentang tanggal tidak memiliki hari kerja.',
            ]);
        }

        $leaveType = LeaveType::query()->findOrFail($data['leave_type_id']);
        abort_unless($leaveType->is_active, 422, 'Jenis cuti tidak aktif.');

        $balance = LeaveBalances::for($employee->id, $leaveType->id, $start->year);
        $remaining = $balance->remaining_days;

        if ($remaining < $workingDays) {
            throw ValidationException::withMessages([
                'end_date' => 'Saldo cuti tidak mencukupi.',
            ]);
        }

        [$teamAvailable, $teamTotal] = $this->teamStats($employee, $start, $end);

        $payload = [
            'code' => CodeGenerator::uniqueLeaveCode(
                CodeGenerator::leaveRequest($employee),
            ),
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'working_days' => $workingDays,
            'reason' => $data['reason'],
            'attachment_url' => $data['attachment_url'] ?? null,
            'status' => LeaveRequest::STATUS_PENDING,
            'balance_before' => $remaining,
            'balance_after' => $remaining - $workingDays,
            'team_available' => $teamAvailable,
            'team_total' => $teamTotal,
        ];

        $leave = LeaveRequest::query()->create($payload);

        NotificationService::notifyManagers(
            'Pengajuan cuti baru',
            sprintf(
                '%s mengajukan %s untuk %d hari kerja (%s s.d. %s).',
                $employee->name,
                $leaveType->name,
                $workingDays,
                $start->toDateString(),
                $end->toDateString(),
            ),
            'leave',
            ['leave_request_id' => $leave->id],
            $employee->id,
        );

        return (new LeaveRequestResource(
            $leave->load(['employee', 'leaveType', 'reviewer']),
        ))->response()->setStatusCode(201);
    }

    public function show(Request $request, LeaveRequest $leaveRequest): LeaveRequestResource
    {
        $this->ensureSelfOrManager($request, $leaveRequest->employee_id);

        return new LeaveRequestResource(
            $leaveRequest->load(['employee', 'leaveType', 'reviewer']),
        );
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest): LeaveRequestResource
    {
        $actor = $request->user();

        abort_unless(
            $actor->id === $leaveRequest->employee_id,
            403,
            'Hanya pemilik pengajuan yang dapat membatalkan.',
        );
        abort_unless($leaveRequest->isPending(), 422, 'Pengajuan sudah diproses.');

        $leaveRequest->fill([
            'status' => LeaveRequest::STATUS_CANCELLED,
            'reviewed_at' => Carbon::now(),
            'review_note' => 'Dibatalkan oleh karyawan.',
        ])->save();

        NotificationService::notifyManagers(
            'Pengajuan cuti dibatalkan',
            sprintf('%s membatalkan pengajuan cuti %s.', $actor->name, $leaveRequest->code),
            'leave',
            ['leave_request_id' => $leaveRequest->id, 'status' => 'cancelled'],
            $actor->id,
        );

        return new LeaveRequestResource(
            $leaveRequest->load(['employee', 'leaveType', 'reviewer']),
        );
    }

    public function review(ReviewRequest $request, LeaveRequest $leaveRequest): LeaveRequestResource
    {
        $reviewer = $this->ensureManager($request);
        $data = $request->validated();

        abort_unless($leaveRequest->isPending(), 422, 'Pengajuan sudah diproses.');

        DB::transaction(function () use ($leaveRequest, $data, $reviewer) {
            if ($data['status'] === LeaveRequest::STATUS_APPROVED) {
                $balance = LeaveBalances::for(
                    $leaveRequest->employee_id,
                    $leaveRequest->leave_type_id,
                    (int) $leaveRequest->start_date->year,
                );

                if ($balance->remaining_days < $leaveRequest->working_days) {
                    throw ValidationException::withMessages([
                        'status' => 'Saldo cuti tidak mencukupi untuk disetujui.',
                    ]);
                }

                $balance->increment('used_days', $leaveRequest->working_days);
            }

            $leaveRequest->fill([
                'status' => $data['status'],
                'reviewer_id' => $reviewer->id,
                'reviewed_at' => Carbon::now(),
                'review_note' => (string) ($data['review_note'] ?? ''),
            ])->save();
        });

        NotificationService::send(
            $leaveRequest->employee,
            $data['status'] === LeaveRequest::STATUS_APPROVED
                ? 'Pengajuan cuti disetujui'
                : 'Pengajuan cuti ditolak',
            sprintf(
                '%s %s oleh %s.%s',
                $leaveRequest->leave_type?->name ?? 'Cuti',
                $data['status'] === LeaveRequest::STATUS_APPROVED ? 'disetujui' : 'ditolak',
                $reviewer->name,
                filled($data['review_note'] ?? null) ? ' Catatan: '.$data['review_note'] : '',
            ),
            'leave',
            ['leave_request_id' => $leaveRequest->id, 'status' => $data['status']],
        );

        return new LeaveRequestResource(
            $leaveRequest->refresh()->load(['employee', 'leaveType', 'reviewer']),
        );
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function teamStats(User $employee, Carbon $start, Carbon $end): array
    {
        $teamQuery = User::query()->employees()->active();
        if ($employee->team !== '') {
            $teamQuery->where('team', $employee->team);
        }
        $teamTotal = $teamQuery->count();

        $onLeave = LeaveRequest::query()
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->whereHas('employee', function ($query) use ($employee) {
                if ($employee->team !== '') {
                    $query->where('team', $employee->team);
                }
            })
            ->distinct()
            ->count('employee_id');

        return [max(0, $teamTotal - $onLeave), $teamTotal];
    }
}
