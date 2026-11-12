<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesRoles;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckInRequest;
use App\Http\Requests\CheckOutRequest;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\OfficeLocation;
use App\Models\OfficeSetting;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Support\DateHelper;
use App\Support\FaceMatcher;
use App\Support\Geo;
use App\Support\Uploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    use AuthorizesRoles;

    public function today(Request $request): JsonResponse
    {
        $employee = $this->targetEmployee($request);

        $record = Attendance::query()
            ->with('user')
            ->where('user_id', $employee->id)
            ->whereDate('date', Carbon::today())
            ->first();

        return response()->json([
            'data' => $record
                ? (new AttendanceResource($record))->resolve($request)
                : null,
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $actor = $request->user();
        $query = Attendance::query()->with('user');

        if ($actor->isManager()) {
            if ($request->filled('employee_id')) {
                $query->where('user_id', (int) $request->query('employee_id'));
            }
        } else {
            $query->where('user_id', $actor->id);
        }

        if ($request->filled('date')) {
            $date = Carbon::parse($request->query('date'))->toDateString();
            $query->whereDate('date', $date);
        } else {
            $from = $request->query('from', Carbon::today()->subDays(30)->toDateString());
            $to = $request->query('to', Carbon::today()->toDateString());
            $query->whereBetween('date', [$from, $to]);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $limit = min((int) $request->query('limit', 200), 500);

        return AttendanceResource::collection(
            $query->orderByDesc('date')->limit($limit)->get(),
        );
    }

    public function checkIn(CheckInRequest $request): JsonResponse
    {
        $user = $request->user();
        $today = Carbon::today();
        $settings = OfficeSetting::current();
        $location = $user->effectiveOfficeLocation();

        $attendance = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        if ($attendance?->check_in_at !== null) {
            throw ValidationException::withMessages([
                'check_in_at' => 'Anda sudah absen masuk hari ini.',
            ]);
        }

        $this->enforceAntiFraud($request, $settings);

        $distance = $this->distanceFromOffice($request, $settings, $location);
        $faceScore = $this->verifyFace($user, $request, $settings);

        $photo = $request->input('photo');
        if ($request->hasFile('photo_file')) {
            $photo = Uploads::storePrivate($request->file('photo_file'), 'attendance');
        }

        if ($settings->selfie_required && $photo === null) {
            throw ValidationException::withMessages([
                'photo_file' => 'Foto selfie wajib diambil untuk absen masuk.',
            ]);
        }

        $shift = ShiftAssignment::query()
            ->with('shift')
            ->where('employee_id', $user->id)
            ->whereDate('date', $today)
            ->first()?->shift;

        $scheduledStart = DateHelper::atTime(
            Carbon::now(),
            $shift?->start_time ?? $settings->work_start,
        );

        $earliest = $scheduledStart->copy()->subMinutes($settings->early_check_in_minutes);
        if (Carbon::now()->lt($earliest)) {
            throw ValidationException::withMessages([
                'check_in_at' => sprintf(
                    'Absen masuk baru dibuka pukul %s.',
                    $earliest->format('H:i'),
                ),
            ]);
        }

        $lateThreshold = $scheduledStart->copy()->addMinutes($settings->late_tolerance_minutes);
        $status = Carbon::now()->gt($lateThreshold) ? 'late' : 'present';

        $attendance ??= new Attendance([
            'user_id' => $user->id,
            'date' => $today->toDateString(),
        ]);

        $attendance->fill([
            'check_in_at' => Carbon::now(),
            'check_in_lat' => $request->input('latitude'),
            'check_in_lng' => $request->input('longitude'),
            'check_in_photo' => $photo,
            'check_in_distance' => $distance,
            'is_mocked' => $request->boolean('is_mocked'),
            'liveness_passed' => $this->livenessPassed($request),
            'face_match_score' => $faceScore,
            'device_id' => $this->deviceId($request),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'status' => $status,
            'note' => (string) ($request->input('note') ?? $attendance->note ?? ''),
        ])->save();

        return response()->json([
            'data' => (new AttendanceResource($attendance->load('user')))->resolve($request),
        ], 201);
    }

    public function checkOut(CheckOutRequest $request): JsonResponse
    {
        $user = $request->user();
        $today = Carbon::today();
        $settings = OfficeSetting::current();
        $location = $user->effectiveOfficeLocation();

        $attendance = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        if ($attendance === null || $attendance->check_in_at === null) {
            throw ValidationException::withMessages([
                'check_out_at' => 'Anda belum absen masuk hari ini.',
            ]);
        }

        if ($attendance->check_out_at !== null) {
            throw ValidationException::withMessages([
                'check_out_at' => 'Anda sudah absen pulang hari ini.',
            ]);
        }

        $this->enforceAntiFraud($request, $settings);

        $distance = $this->distanceFromOffice($request, $settings, $location);
        $faceScore = $this->verifyFace($user, $request, $settings);

        $photo = $request->input('photo');
        if ($request->hasFile('photo_file')) {
            $photo = Uploads::storePrivate($request->file('photo_file'), 'attendance');
        }

        if ($settings->selfie_required && $photo === null) {
            throw ValidationException::withMessages([
                'photo_file' => 'Foto selfie wajib diambil untuk absen pulang.',
            ]);
        }

        $attendance->fill([
            'check_out_at' => Carbon::now(),
            'check_out_lat' => $request->input('latitude'),
            'check_out_lng' => $request->input('longitude'),
            'check_out_photo' => $photo,
            'check_out_distance' => $distance,
            'is_mocked' => $attendance->is_mocked || $request->boolean('is_mocked'),
            'liveness_passed' => $attendance->liveness_passed && $this->livenessPassed($request),
            'face_match_score' => $attendance->face_match_score ?? $faceScore,
            'device_id' => $attendance->device_id ?? $this->deviceId($request),
            'ip_address' => $attendance->ip_address ?? $request->ip(),
            'user_agent' => $attendance->user_agent ?? substr((string) $request->userAgent(), 0, 1000),
            'note' => (string) ($request->input('note') ?? $attendance->note ?? ''),
        ])->save();

        return response()->json([
            'data' => (new AttendanceResource($attendance->load('user')))->resolve($request),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $date = Carbon::parse($request->query('date', Carbon::today()->toDateString()))->startOfDay();

        $employees = User::query()->employees()->active()->orderBy('name')->get();
        $records = Attendance::query()
            ->whereDate('date', $date)
            ->get()
            ->keyBy('user_id');

        $leaveByEmployee = LeaveRequest::query()
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->get()
            ->keyBy('employee_id');

        $rows = [];
        $counts = ['present' => 0, 'late' => 0, 'completed' => 0, 'on_leave' => 0, 'absent' => 0];

        foreach ($employees as $employee) {
            $record = $records->get($employee->id);
            $onLeave = $leaveByEmployee->has($employee->id);

            if ($record?->check_in_at !== null) {
                $counts['present']++;
                if ($record->status === 'late') {
                    $counts['late']++;
                }
                if ($record->check_out_at !== null) {
                    $counts['completed']++;
                }
            } elseif ($onLeave) {
                $counts['on_leave']++;
            } else {
                $counts['absent']++;
            }

            $rows[] = [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'nip' => (string) ($employee->nip ?? ''),
                'team' => (string) ($employee->team ?? ''),
                'attendance' => $record
                    ? (new AttendanceResource($record->load('user')))->resolve($request)
                    : null,
                'on_leave' => $onLeave,
            ];
        }

        return response()->json([
            'data' => [
                'date' => $date->toDateString(),
                'total_employees' => $employees->count(),
                'counts' => $counts,
                'employees' => $rows,
            ],
        ]);
    }

    private function targetEmployee(Request $request): User
    {
        $actor = $request->user();

        if ($request->filled('employee_id')) {
            abort_unless($actor->isManager(), 403, 'Anda tidak memiliki akses ke data ini.');

            return User::query()->findOrFail((int) $request->query('employee_id'));
        }

        return $actor;
    }

    private function distanceFromOffice(
        Request $request,
        OfficeSetting $settings,
        ?OfficeLocation $location,
    ): int {
        $officeLat = $location?->latitude ?? $settings->latitude;
        $officeLng = $location?->longitude ?? $settings->longitude;
        $radius = $location?->radius_meters ?? $settings->radius_meters;
        $officeName = $location?->name ?? $settings->office_name;

        $distance = (int) round(Geo::distanceMeters(
            (float) $request->input('latitude'),
            (float) $request->input('longitude'),
            (float) $officeLat,
            (float) $officeLng,
        ));

        if ($distance > $radius) {
            throw ValidationException::withMessages([
                'latitude' => sprintf(
                    'Lokasi Anda %d m dari %s. Maksimal %d m.',
                    $distance,
                    $officeName,
                    $radius,
                ),
            ]);
        }

        return $distance;
    }

    private function enforceAntiFraud(Request $request, OfficeSetting $settings): void
    {
        if ($settings->mock_location_blocked && $request->boolean('is_mocked')) {
            throw ValidationException::withMessages([
                'is_mocked' => 'Lokasi palsu (mock GPS) terdeteksi. Matikan aplikasi lokasi palsu.',
            ]);
        }

        if (! $this->livenessPassed($request)) {
            throw ValidationException::withMessages([
                'liveness_passed' => 'Verifikasi liveness gagal. Ulangi pengambilan selfie.',
            ]);
        }
    }

    private function livenessPassed(Request $request): bool
    {
        return ! $request->has('liveness_passed') || $request->boolean('liveness_passed');
    }

    private function deviceId(Request $request): ?string
    {
        $deviceId = trim((string) ($request->header('X-Device-Id') ?? $request->input('device_id', '')));

        return $deviceId !== '' ? $deviceId : null;
    }

    private function verifyFace(User $user, Request $request, OfficeSetting $settings): ?int
    {
        if (! $settings->face_match_required) {
            return null;
        }

        if (! $user->hasEnrolledFace()) {
            throw ValidationException::withMessages([
                'photo_file' => 'Wajah belum terdaftar. Daftarkan wajah terlebih dahulu di Profil.',
            ]);
        }

        $file = $request->file('photo_file');
        if ($file === null) {
            throw ValidationException::withMessages([
                'photo_file' => 'Foto selfie wajib diunggah untuk verifikasi wajah.',
            ]);
        }

        $hash = FaceMatcher::hashFile($file->getRealPath());
        if ($hash === null) {
            throw ValidationException::withMessages([
                'photo_file' => 'Foto tidak dapat dibaca untuk verifikasi wajah.',
            ]);
        }

        $score = FaceMatcher::distance((string) $user->face_hash, $hash);
        if ($score > $settings->face_match_threshold) {
            throw ValidationException::withMessages([
                'photo_file' => sprintf(
                    'Wajah tidak cocok dengan data terdaftar (skor %d, maksimal %d).',
                    $score,
                    $settings->face_match_threshold,
                ),
            ]);
        }

        return $score;
    }
}
