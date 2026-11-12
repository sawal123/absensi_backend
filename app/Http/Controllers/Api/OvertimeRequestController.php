<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesRoles;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewRequest;
use App\Http\Requests\StoreOvertimeRequest;
use App\Http\Resources\OvertimeRequestResource;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class OvertimeRequestController extends Controller
{
    use AuthorizesRoles;

    public function index(Request $request): AnonymousResourceCollection
    {
        $actor = $request->user();
        $scope = $request->query('scope', 'mine');

        $query = OvertimeRequest::query()
            ->with(['employee', 'reviewer'])
            ->orderByDesc('date');

        if ($scope === 'team' && $actor->isManager()) {
            $query->whereIn('employee_id', User::query()->employees()->select('id'));
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
            $query->whereYear('date', (int) $request->query('year'));
        }

        if ($request->filled('month')) {
            $query->whereMonth('date', (int) $request->query('month'));
        }

        return OvertimeRequestResource::collection($query->get());
    }

    public function store(StoreOvertimeRequest $request): JsonResponse
    {
        $employee = $request->user();
        $data = $request->validated();

        $start = Carbon::parse($data['start_at']);
        $end = Carbon::parse($data['end_at']);
        $minutes = (int) round($start->diffInMinutes($end));

        if ($minutes < 15) {
            throw ValidationException::withMessages([
                'end_at' => 'Durasi lembur minimal 15 menit.',
            ]);
        }

        $overtime = OvertimeRequest::query()->create([
            'employee_id' => $employee->id,
            'date' => Carbon::parse($data['date'])->toDateString(),
            'start_at' => $start,
            'end_at' => $end,
            'minutes' => $minutes,
            'reason' => $data['reason'],
            'status' => OvertimeRequest::STATUS_PENDING,
        ]);

        NotificationService::notifyManagers(
            'Pengajuan lembur baru',
            sprintf('%s mengajukan lembur %d menit pada %s.', $employee->name, $minutes, $overtime->date->toDateString()),
            'overtime',
            ['overtime_request_id' => $overtime->id],
            $employee->id,
        );

        return (new OvertimeRequestResource($overtime->load(['employee', 'reviewer'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, OvertimeRequest $overtimeRequest): OvertimeRequestResource
    {
        $this->ensureSelfOrManager($request, $overtimeRequest->employee_id);

        return new OvertimeRequestResource($overtimeRequest->load(['employee', 'reviewer']));
    }

    public function cancel(Request $request, OvertimeRequest $overtimeRequest): OvertimeRequestResource
    {
        abort_unless(
            $request->user()->id === $overtimeRequest->employee_id,
            403,
            'Hanya pemilik pengajuan yang dapat membatalkan.',
        );
        abort_unless($overtimeRequest->isPending(), 422, 'Pengajuan sudah diproses.');

        $overtimeRequest->fill([
            'status' => OvertimeRequest::STATUS_CANCELLED,
            'reviewed_at' => now(),
            'review_note' => 'Dibatalkan oleh karyawan.',
        ])->save();

        return new OvertimeRequestResource($overtimeRequest->load(['employee', 'reviewer']));
    }

    public function review(ReviewRequest $request, OvertimeRequest $overtimeRequest): OvertimeRequestResource
    {
        $reviewer = $this->ensureManager($request);
        $data = $request->validated();

        abort_unless($overtimeRequest->isPending(), 422, 'Pengajuan sudah diproses.');

        $overtimeRequest->fill([
            'status' => $data['status'],
            'reviewer_id' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => (string) ($data['review_note'] ?? ''),
        ])->save();

        NotificationService::send(
            $overtimeRequest->employee,
            $data['status'] === OvertimeRequest::STATUS_APPROVED
                ? 'Lembur disetujui'
                : 'Lembur ditolak',
            sprintf(
                'Pengajuan lembur %s %s.',
                $overtimeRequest->date->toDateString(),
                $data['status'] === OvertimeRequest::STATUS_APPROVED ? 'disetujui' : 'ditolak',
            ),
            'overtime',
            ['overtime_request_id' => $overtimeRequest->id, 'status' => $data['status']],
        );

        return new OvertimeRequestResource($overtimeRequest->refresh()->load(['employee', 'reviewer']));
    }
}
