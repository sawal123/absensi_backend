<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesRoles;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewRequest;
use App\Http\Requests\StoreCorrectionRequest;
use App\Http\Resources\AttendanceCorrectionResource;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\OfficeSetting;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\CodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceCorrectionController extends Controller
{
    use AuthorizesRoles;

    public function index(Request $request): AnonymousResourceCollection
    {
        $actor = $request->user();
        $scope = $request->query('scope', 'mine');

        $query = AttendanceCorrection::query()
            ->with(['employee', 'reviewer'])
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

        return AttendanceCorrectionResource::collection($query->get());
    }

    public function store(StoreCorrectionRequest $request): JsonResponse
    {
        $employee = $request->user();
        $data = $request->validated();
        $date = Carbon::parse($data['date'])->startOfDay();
        $office = OfficeSetting::current();

        $attendance = null;
        if (! empty($data['attendance_id'])) {
            $attendance = Attendance::query()->findOrFail($data['attendance_id']);
            abort_unless(
                $attendance->user_id === $employee->id,
                403,
                'Data absensi bukan milik Anda.',
            );
        } else {
            $attendance = Attendance::query()
                ->where('user_id', $employee->id)
                ->whereDate('date', $date)
                ->first();
        }

        $shift = ShiftAssignment::query()
            ->with('shift')
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date)
            ->first()?->shift;

        $correction = AttendanceCorrection::query()->create([
            'code' => CodeGenerator::uniqueCorrectionCode(
                CodeGenerator::correction($employee),
            ),
            'employee_id' => $employee->id,
            'attendance_id' => $attendance?->id,
            'date' => $date->toDateString(),
            'scheduled_start' => $shift?->start_time ?? $office->work_start,
            'scheduled_end' => $shift?->end_time ?? $office->work_end,
            'recorded_check_in' => $attendance?->check_in_at,
            'recorded_check_out' => $attendance?->check_out_at,
            'proposed_check_in' => $data['proposed_check_in_at'] ?? null,
            'proposed_check_out' => $data['proposed_check_out_at'] ?? null,
            'correction_type' => $data['correction_type'],
            'reason' => $data['reason'],
            'evidence_url' => $data['evidence_url'] ?? null,
            'evidence_name' => $data['evidence_name'] ?? null,
            'evidence_meta' => $data['evidence_meta'] ?? null,
            'status' => AttendanceCorrection::STATUS_PENDING,
        ]);

        NotificationService::notifyManagers(
            'Pengajuan perbaikan absensi',
            sprintf(
                '%s mengajukan perbaikan absensi tanggal %s.',
                $employee->name,
                $date->toDateString(),
            ),
            'correction',
            ['attendance_correction_id' => $correction->id],
            $employee->id,
        );

        return (new AttendanceCorrectionResource(
            $correction->load(['employee', 'reviewer']),
        ))->response()->setStatusCode(201);
    }

    public function show(
        Request $request,
        AttendanceCorrection $attendanceCorrection,
    ): AttendanceCorrectionResource {
        $this->ensureSelfOrManager($request, $attendanceCorrection->employee_id);

        return new AttendanceCorrectionResource(
            $attendanceCorrection->load(['employee', 'reviewer']),
        );
    }

    public function cancel(
        Request $request,
        AttendanceCorrection $attendanceCorrection,
    ): AttendanceCorrectionResource {
        $actor = $request->user();

        abort_unless(
            $actor->id === $attendanceCorrection->employee_id,
            403,
            'Hanya pemilik pengajuan yang dapat membatalkan.',
        );
        abort_unless(
            $attendanceCorrection->isPending(),
            422,
            'Pengajuan sudah diproses.',
        );

        $attendanceCorrection->fill([
            'status' => AttendanceCorrection::STATUS_CANCELLED,
            'reviewed_at' => Carbon::now(),
            'review_note' => 'Dibatalkan oleh karyawan.',
        ])->save();

        NotificationService::notifyManagers(
            'Perbaikan absensi dibatalkan',
            sprintf('%s membatalkan pengajuan %s.', $actor->name, $attendanceCorrection->code),
            'correction',
            ['attendance_correction_id' => $attendanceCorrection->id, 'status' => 'cancelled'],
            $actor->id,
        );

        return new AttendanceCorrectionResource(
            $attendanceCorrection->load(['employee', 'reviewer']),
        );
    }

    public function review(
        ReviewRequest $request,
        AttendanceCorrection $attendanceCorrection,
    ): AttendanceCorrectionResource {
        $reviewer = $this->ensureManager($request);
        $data = $request->validated();

        abort_unless(
            $attendanceCorrection->isPending(),
            422,
            'Pengajuan sudah diproses.',
        );

        DB::transaction(function () use ($attendanceCorrection, $data, $reviewer) {
            if ($data['status'] === AttendanceCorrection::STATUS_APPROVED) {
                $attendance = $attendanceCorrection->attendance
                    ?? Attendance::query()
                        ->where('user_id', $attendanceCorrection->employee_id)
                        ->whereDate('date', $attendanceCorrection->date)
                        ->first()
                    ?? new Attendance([
                        'user_id' => $attendanceCorrection->employee_id,
                        'date' => $attendanceCorrection->date->toDateString(),
                    ]);

                if ($attendanceCorrection->proposed_check_in !== null) {
                    $attendance->check_in_at = $attendanceCorrection->proposed_check_in;
                }
                if ($attendanceCorrection->proposed_check_out !== null) {
                    $attendance->check_out_at = $attendanceCorrection->proposed_check_out;
                }
                if ($attendance->status === null || $attendance->status === '') {
                    $attendance->status = 'present';
                }
                $attendance->save();

                $attendanceCorrection->attendance_id = $attendance->id;
            }

            $attendanceCorrection->fill([
                'status' => $data['status'],
                'reviewer_id' => $reviewer->id,
                'reviewed_at' => Carbon::now(),
                'review_note' => (string) ($data['review_note'] ?? ''),
            ])->save();
        });

        NotificationService::send(
            $attendanceCorrection->employee,
            $data['status'] === AttendanceCorrection::STATUS_APPROVED
                ? 'Perbaikan absensi disetujui'
                : 'Perbaikan absensi ditolak',
            sprintf(
                'Pengajuan perbaikan %s %s oleh %s.%s',
                $attendanceCorrection->date->toDateString(),
                $data['status'] === AttendanceCorrection::STATUS_APPROVED ? 'disetujui' : 'ditolak',
                $reviewer->name,
                filled($data['review_note'] ?? null) ? ' Catatan: '.$data['review_note'] : '',
            ),
            'correction',
            ['attendance_correction_id' => $attendanceCorrection->id, 'status' => $data['status']],
        );

        return new AttendanceCorrectionResource(
            $attendanceCorrection->refresh()->load(['employee', 'reviewer']),
        );
    }
}
