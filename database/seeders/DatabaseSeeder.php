<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OfficeLocation;
use App\Models\OfficeSetting;
use App\Models\OvertimeRequest;
use App\Models\PayrollDeductionRule;
use App\Models\PayrollSetting;
use App\Models\SalaryAllowance;
use App\Models\SalaryProfile;
use App\Models\ShiftAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkShift;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    /** @var array<int, array{name: string, email: string, nip: string, annual: int, permission: int, sick: int}> */
    private array $employees = [
        ['name' => 'Aditya Pratama', 'email' => 'aditya@absensi.com', 'nip' => '0248', 'annual' => 3, 'permission' => 1, 'sick' => 0],
        ['name' => 'Budi Santoso', 'email' => 'budi@absensi.com', 'nip' => '0251', 'annual' => 12, 'permission' => 0, 'sick' => 1],
        ['name' => 'Citra Lestari', 'email' => 'citra@absensi.com', 'nip' => '0256', 'annual' => 2, 'permission' => 0, 'sick' => 0],
        ['name' => 'Dimas Prakoso', 'email' => 'dimas@absensi.com', 'nip' => '0260', 'annual' => 4, 'permission' => 1, 'sick' => 0],
        ['name' => 'Eko Saputra', 'email' => 'eko@absensi.com', 'nip' => '0262', 'annual' => 0, 'permission' => 0, 'sick' => 0],
        ['name' => 'Fajar Ramadhan', 'email' => 'fajar@absensi.com', 'nip' => '0265', 'annual' => 1, 'permission' => 0, 'sick' => 0],
        ['name' => 'Rina Wulandari', 'email' => 'rina@absensi.com', 'nip' => '0268', 'annual' => 4, 'permission' => 0, 'sick' => 0],
        ['name' => 'Nanda Putri', 'email' => 'nanda@absensi.com', 'nip' => '0271', 'annual' => 2, 'permission' => 0, 'sick' => 1],
    ];

    public function run(): void
    {
        TenantContext::clear();
        $this->call(SuperAdminSeeder::class);

        $tenant = Tenant::query()->updateOrCreate(
            ['id' => 1],
            [
                'name' => 'Absensi Demo',
                'code' => 'demo',
                'plan' => 'basic',
                'status' => Tenant::STATUS_ACTIVE,
                'trial_ends_at' => null,
                'subscription_ends_at' => null,
                'max_employees' => 100,
            ],
        );

        TenantContext::set($tenant);
        OfficeSetting::current();

        $this->seedOfficeLocations();
        $this->seedUsers();
        $this->assignOffices();
        $this->seedLeaveTypes();
        $this->seedBalances();
        $this->seedShifts();
        $this->seedAssignments();
        $this->seedPayroll();
        $this->seedAttendance();
        $this->seedRequests();
        $this->seedHolidays();
        $this->seedOvertime();

        TenantContext::clear();
    }

    private function seedOfficeLocations(): void
    {
        OfficeLocation::query()->updateOrCreate(
            ['name' => 'Kantor Pusat'],
            [
                'address' => 'Jakarta',
                'latitude' => -6.2000000,
                'longitude' => 106.8166660,
                'radius_meters' => 100,
                'timezone' => 'Asia/Jakarta',
                'is_default' => true,
                'is_active' => true,
            ],
        );

        OfficeLocation::query()->updateOrCreate(
            ['name' => 'Cabang Bandung'],
            [
                'address' => 'Bandung',
                'latitude' => -6.9174640,
                'longitude' => 107.6191230,
                'radius_meters' => 150,
                'timezone' => 'Asia/Jakarta',
                'is_default' => false,
                'is_active' => true,
            ],
        );
    }

    private function assignOffices(): void
    {
        $defaultId = OfficeLocation::defaultLocation()?->id;
        if ($defaultId !== null) {
            User::query()->whereNull('office_location_id')->update(['office_location_id' => $defaultId]);
        }
    }

    private function seedUsers(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@absensi.com'],
            [
                'name' => 'Administrator',
                'password' => 'admin123',
                'role' => User::ROLE_ADMIN,
                'nip' => '0210',
                'position' => 'Atasan',
                'team' => 'Tim Operasional',
                'is_active' => true,
                'joined_at' => Carbon::now()->subYears(3)->toDateString(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'dewi@absensi.com'],
            [
                'name' => 'Dewi Puspita',
                'password' => '123456',
                'role' => User::ROLE_SUPERVISOR,
                'nip' => '0199',
                'position' => 'Supervisor',
                'team' => 'Tim Operasional',
                'is_active' => true,
                'joined_at' => Carbon::now()->subYears(2)->toDateString(),
            ],
        );

        foreach ($this->employees as $employee) {
            User::query()->updateOrCreate(
                ['email' => $employee['email']],
                [
                    'name' => $employee['name'],
                    'password' => '123456',
                    'role' => User::ROLE_EMPLOYEE,
                    'nip' => $employee['nip'],
                    'position' => 'Staff Operasional',
                    'team' => 'Tim Operasional',
                    'is_active' => true,
                    'joined_at' => Carbon::now()->subYear()->toDateString(),
                ],
            );
        }
    }

    private function seedLeaveTypes(): void
    {
        $types = [
            ['name' => 'Cuti tahunan', 'is_paid' => true, 'max_days_per_year' => 12, 'requires_attachment' => false],
            ['name' => 'Izin', 'is_paid' => true, 'max_days_per_year' => 3, 'requires_attachment' => false],
            ['name' => 'Sakit', 'is_paid' => true, 'max_days_per_year' => 12, 'requires_attachment' => true],
        ];

        foreach ($types as $type) {
            LeaveType::query()->updateOrCreate(['name' => $type['name']], $type + ['is_active' => true]);
        }
    }

    private function seedBalances(): void
    {
        $year = Carbon::now()->year;
        $types = LeaveType::query()->orderBy('id')->get();

        foreach ($this->employees as $employee) {
            $user = User::query()->where('email', $employee['email'])->first();
            if ($user === null) {
                continue;
            }

            $usedByType = [1 => $employee['annual'], 2 => $employee['permission'], 3 => $employee['sick']];
            $index = 1;

            foreach ($types as $type) {
                LeaveBalance::query()->updateOrCreate(
                    [
                        'employee_id' => $user->id,
                        'leave_type_id' => $type->id,
                        'year' => $year,
                    ],
                    [
                        'entitled_days' => $type->max_days_per_year,
                        'used_days' => $usedByType[$index] ?? 0,
                        'adjustment_days' => 0,
                    ],
                );
                $index++;
            }
        }
    }

    private function seedShifts(): void
    {
        $shifts = [
            ['name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'break_label' => 'Istirahat 12.00 – 13.00', 'cross_day' => false, 'color' => 0xFF2563EB],
            ['name' => 'Pagi', 'start_time' => '06:00', 'end_time' => '14:00', 'break_label' => 'Istirahat 10.00 – 10.30', 'cross_day' => false, 'color' => 0xFFB45309],
            ['name' => 'Shift malam', 'start_time' => '22:00', 'end_time' => '06:00', 'break_label' => null, 'cross_day' => true, 'color' => 0xFF4F46E5],
        ];

        foreach ($shifts as $shift) {
            WorkShift::query()->updateOrCreate(
                ['name' => $shift['name']],
                $shift + ['office' => 'Kantor Pusat', 'is_active' => true],
            );
        }
    }

    private function seedAssignments(): void
    {
        $employees = User::query()->employees()->get();
        $shifts = WorkShift::query()->get()->keyBy('name');

        $regular = $shifts->get('Reguler');
        $night = $shifts->get('Shift malam');
        if ($regular === null || $night === null) {
            return;
        }

        $start = Carbon::now()->startOfWeek()->subWeeks(2);
        $end = Carbon::now()->startOfWeek()->addWeeks(10);

        foreach ($employees as $employee) {
            for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
                if ($day->isSunday()) {
                    continue;
                }

                $shift = $day->isFriday() ? $night : $regular;

                ShiftAssignment::query()->firstOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'date' => $day->toDateString(),
                    ],
                    ['shift_id' => $shift->id],
                );
            }
        }
    }

    private function seedPayroll(): void
    {
        PayrollSetting::current();

        $rules = [
            ['name' => 'Keterlambatan', 'basis' => 'late_per_occurrence', 'rate' => 25000, 'is_system' => false],
            ['name' => 'Absen tanpa keterangan', 'basis' => 'absence_daily_wage', 'rate' => 100, 'is_system' => false],
            ['name' => 'Cuti tidak dibayar', 'basis' => 'unpaid_leave_daily_wage', 'rate' => 100, 'is_system' => false],
            ['name' => 'BPJS Kesehatan', 'basis' => 'percent_gross', 'rate' => 1, 'is_system' => false],
            ['name' => 'BPJS JHT', 'basis' => 'percent_gross', 'rate' => 2, 'is_system' => false],
            ['name' => 'BPJS JP', 'basis' => 'percent_gross', 'rate' => 1, 'is_system' => false],
            ['name' => 'PPh21', 'basis' => 'pph21', 'rate' => 0, 'is_system' => true],
        ];

        foreach ($rules as $index => $rule) {
            PayrollDeductionRule::query()->updateOrCreate(
                ['name' => $rule['name']],
                $rule + ['is_active' => true, 'sort_order' => $index],
            );
        }

        foreach (User::query()->employees()->get() as $employee) {
            SalaryProfile::query()->updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'base_salary' => 5000000,
                    'overtime_rate' => 30000,
                    'ptkp_status' => 'TK/0',
                    'npwp' => '',
                ],
            );

            if ($employee->salaryAllowances()->exists()) {
                continue;
            }

            $allowances = [
                ['name' => 'Tunjangan jabatan', 'amount' => 500000, 'basis' => 'monthly', 'sort_order' => 0],
                ['name' => 'Uang makan', 'amount' => 25000, 'basis' => 'per_present_day', 'sort_order' => 1],
                ['name' => 'Transport', 'amount' => 20000, 'basis' => 'per_present_day', 'sort_order' => 2],
            ];

            foreach ($allowances as $allowance) {
                SalaryAllowance::query()->create($allowance + ['employee_id' => $employee->id]);
            }
        }
    }

    private function seedAttendance(): void
    {
        $periodStart = Carbon::now()->startOfMonth()->subMonthNoOverflow();
        $periodEnd = $periodStart->copy()->endOfMonth();
        $lateOffsets = [0, 5, 12, 20, 3, 0, 15];

        foreach (User::query()->employees()->get() as $employee) {
            for ($day = $periodStart->copy(); $day->lte($periodEnd); $day->addDay()) {
                if ($day->isWeekend()) {
                    continue;
                }
                if ($day->isTuesday() && $day->day >= 8) {
                    continue;
                }

                $shift = ShiftAssignment::query()
                    ->with('shift')
                    ->where('employee_id', $employee->id)
                    ->whereDate('date', $day)
                    ->first()?->shift;

                $startTime = $shift?->start_time ?? '08:00';
                $endTime = $shift?->end_time ?? '17:00';

                $checkIn = Carbon::parse($day->toDateString().' '.$startTime)
                    ->addMinutes($lateOffsets[($day->day - 1) % count($lateOffsets)]);
                $checkOut = Carbon::parse($day->toDateString().' '.$endTime)
                    ->addMinutes(10 + ($day->day % 5) * 9);

                Attendance::query()->updateOrCreate(
                    [
                        'user_id' => $employee->id,
                        'date' => $day->toDateString(),
                    ],
                    [
                        'check_in_at' => $checkIn,
                        'check_out_at' => $checkOut,
                        'status' => $checkIn->gt(Carbon::parse($day->toDateString().' '.$startTime)) ? 'late' : 'present',
                        'note' => 'Data demo penggajian',
                    ],
                );
            }
        }
    }

    private function seedRequests(): void
    {
        $today = Carbon::today();
        $employees = User::query()->employees()->get()->keyBy('email');
        $types = LeaveType::query()->get()->keyBy('name');

        $leaveRequests = [
            [
                'email' => 'citra@absensi.com',
                'type' => 'Cuti tahunan',
                'start' => $today->copy()->addDays(4),
                'end' => $today->copy()->addDays(5),
                'days' => 2,
                'reason' => 'Menghadiri acara keluarga di Bandung.',
                'status' => LeaveRequest::STATUS_PENDING,
                'submitted' => $today->copy()->subDay()->setTime(16, 20),
            ],
            [
                'email' => 'eko@absensi.com',
                'type' => 'Cuti tahunan',
                'start' => $today->copy()->addDays(7),
                'end' => $today->copy()->addDays(7),
                'days' => 1,
                'reason' => 'Keperluan administrasi kependudukan.',
                'status' => LeaveRequest::STATUS_PENDING,
                'submitted' => $today->copy()->subDays(2)->setTime(8, 45),
            ],
            [
                'email' => 'dimas@absensi.com',
                'type' => 'Izin',
                'start' => $today->copy()->addDays(2),
                'end' => $today->copy()->addDays(2),
                'days' => 1,
                'reason' => 'Izin keperluan pribadi.',
                'status' => LeaveRequest::STATUS_PENDING,
                'submitted' => $today->copy()->subDay()->setTime(14, 5),
            ],
            [
                'email' => 'fajar@absensi.com',
                'type' => 'Sakit',
                'start' => $today->copy()->subDay(),
                'end' => $today->copy()->subDay(),
                'days' => 1,
                'reason' => 'Demam dan dokter menyarankan istirahat.',
                'status' => LeaveRequest::STATUS_PENDING,
                'submitted' => $today->copy()->subDay()->setTime(6, 40),
            ],
            [
                'email' => 'rina@absensi.com',
                'type' => 'Cuti tahunan',
                'start' => $today->copy(),
                'end' => $today->copy(),
                'days' => 1,
                'reason' => 'Pemeriksaan kesehatan rutin.',
                'status' => LeaveRequest::STATUS_APPROVED,
                'submitted' => $today->copy()->subDays(3)->setTime(13, 10),
            ],
            [
                'email' => 'nanda@absensi.com',
                'type' => 'Sakit',
                'start' => $today->copy()->subDays(8),
                'end' => $today->copy()->subDays(7),
                'days' => 2,
                'reason' => 'Rawat jalan dan istirahat sesuai anjuran dokter.',
                'status' => LeaveRequest::STATUS_APPROVED,
                'submitted' => $today->copy()->subDays(9)->setTime(7, 20),
            ],
            [
                'email' => 'budi@absensi.com',
                'type' => 'Cuti tahunan',
                'start' => $today->copy()->subDays(5),
                'end' => $today->copy()->subDays(5),
                'days' => 1,
                'reason' => 'Acara keluarga di luar kota.',
                'status' => LeaveRequest::STATUS_REJECTED,
                'submitted' => $today->copy()->subDays(6)->setTime(10, 5),
            ],
        ];

        foreach ($leaveRequests as $item) {
            $employee = $employees->get($item['email']);
            $type = $types->get($item['type']);
            if ($employee === null || $type === null) {
                continue;
            }

            $reviewer = $item['status'] === LeaveRequest::STATUS_PENDING ? null : User::query()
                ->where('email', 'dewi@absensi.com')
                ->first();

            LeaveRequest::query()->updateOrCreate(
                ['code' => $this->code('CT', $item['start'], (string) $employee->nip)],
                [
                    'employee_id' => $employee->id,
                    'leave_type_id' => $type->id,
                    'start_date' => $item['start']->toDateString(),
                    'end_date' => $item['end']->toDateString(),
                    'working_days' => $item['days'],
                    'reason' => $item['reason'],
                    'status' => $item['status'],
                    'reviewer_id' => $reviewer?->id,
                    'reviewed_at' => $reviewer !== null ? $item['submitted']->copy()->addDay() : null,
                    'review_note' => $item['status'] === LeaveRequest::STATUS_REJECTED ? 'Ditolak · Saldo cuti tidak mencukupi' : '',
                    'balance_before' => 12,
                    'balance_after' => 12 - $item['days'],
                    'team_available' => 7,
                    'team_total' => 8,
                    'created_at' => $item['submitted'],
                ],
            );
        }

        $corrections = [
            [
                'email' => 'aditya@absensi.com',
                'date' => $today->copy()->subDay(),
                'type' => AttendanceCorrection::TYPE_CHECK_OUT,
                'proposed_in' => null,
                'proposed_out' => $today->copy()->subDay()->setTime(17, 5),
                'reason' => 'Lupa absen pulang setelah menyelesaikan rekap stok.',
                'status' => AttendanceCorrection::STATUS_PENDING,
            ],
            [
                'email' => 'citra@absensi.com',
                'date' => $today->copy()->subDays(2),
                'type' => AttendanceCorrection::TYPE_CHECK_IN,
                'proposed_in' => $today->copy()->subDays(2)->setTime(8, 2),
                'proposed_out' => null,
                'reason' => 'Lupa absen masuk karena langsung mengikuti rapat pagi.',
                'status' => AttendanceCorrection::STATUS_PENDING,
            ],
            [
                'email' => 'nanda@absensi.com',
                'date' => $today->copy()->subDays(3),
                'type' => AttendanceCorrection::TYPE_BOTH,
                'proposed_in' => $today->copy()->subDays(3)->setTime(8, 0),
                'proposed_out' => $today->copy()->subDays(3)->setTime(17, 0),
                'reason' => 'Mesin absensi bermasalah saat jam masuk dan pulang.',
                'status' => AttendanceCorrection::STATUS_PENDING,
            ],
            [
                'email' => 'rina@absensi.com',
                'date' => $today->copy()->subDays(5),
                'type' => AttendanceCorrection::TYPE_CHECK_OUT,
                'proposed_in' => null,
                'proposed_out' => $today->copy()->subDays(5)->setTime(17, 3),
                'reason' => 'Mesin absensi pulang tidak merespons.',
                'status' => AttendanceCorrection::STATUS_APPROVED,
            ],
        ];

        foreach ($corrections as $item) {
            $employee = $employees->get($item['email']);
            if ($employee === null) {
                continue;
            }

            $attendance = Attendance::query()
                ->where('user_id', $employee->id)
                ->whereDate('date', $item['date'])
                ->first();

            $reviewer = $item['status'] === AttendanceCorrection::STATUS_PENDING ? null : User::query()
                ->where('email', 'dewi@absensi.com')
                ->first();

            AttendanceCorrection::query()->updateOrCreate(
                ['code' => $this->code('PA', $item['date'], (string) $employee->nip)],
                [
                    'employee_id' => $employee->id,
                    'attendance_id' => $attendance?->id,
                    'date' => $item['date']->toDateString(),
                    'scheduled_start' => '08:00',
                    'scheduled_end' => '17:00',
                    'recorded_check_in' => $attendance?->check_in_at,
                    'recorded_check_out' => $attendance?->check_out_at,
                    'proposed_check_in' => $item['proposed_in'],
                    'proposed_check_out' => $item['proposed_out'],
                    'correction_type' => $item['type'],
                    'reason' => $item['reason'],
                    'status' => $item['status'],
                    'reviewer_id' => $reviewer?->id,
                    'reviewed_at' => $reviewer !== null ? Carbon::now()->subDays(2) : null,
                    'review_note' => $item['status'] === AttendanceCorrection::STATUS_APPROVED ? 'Disetujui · Data absensi diperbarui' : '',
                ],
            );
        }
    }

    private function code(string $prefix, Carbon $date, string $nip): string
    {
        return sprintf('%s-%s-%s', $prefix, $date->format('ymd'), $nip);
    }

    private function seedHolidays(): void
    {
        $holidays = [
            ['date' => '2026-01-01', 'name' => 'Tahun Baru 2026'],
            ['date' => '2026-03-19', 'name' => 'Hari Raya Nyepi'],
            ['date' => '2026-05-01', 'name' => 'Hari Buruh Internasional'],
            ['date' => '2026-08-17', 'name' => 'Hari Kemerdekaan RI'],
            ['date' => '2026-12-25', 'name' => 'Hari Raya Natal'],
        ];

        foreach ($holidays as $holiday) {
            Holiday::query()->updateOrCreate(
                ['date' => $holiday['date'], 'office_location_id' => null],
                ['name' => $holiday['name'], 'is_national' => true],
            );
        }
    }

    private function seedOvertime(): void
    {
        $employee = User::query()->where('email', 'aditya@absensi.com')->first();
        $reviewer = User::query()->where('email', 'dewi@absensi.com')->first();
        if ($employee === null) {
            return;
        }

        $yesterday = Carbon::yesterday();
        $approvedDate = $yesterday->copy()->subDay();

        OvertimeRequest::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'date' => $approvedDate->toDateString(),
            ],
            [
                'start_at' => $approvedDate->copy()->setTime(17, 0),
                'end_at' => $approvedDate->copy()->setTime(19, 0),
                'minutes' => 120,
                'reason' => 'Menyelesaikan rekap stok akhir bulan.',
                'status' => OvertimeRequest::STATUS_APPROVED,
                'reviewer_id' => $reviewer?->id,
                'reviewed_at' => $approvedDate->copy()->setTime(16, 0),
                'review_note' => 'Disetujui.',
            ],
        );

        OvertimeRequest::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'date' => $yesterday->toDateString(),
            ],
            [
                'start_at' => $yesterday->copy()->setTime(17, 0),
                'end_at' => $yesterday->copy()->setTime(18, 30),
                'minutes' => 90,
                'reason' => 'Persiapan audit inventaris.',
                'status' => OvertimeRequest::STATUS_PENDING,
                'reviewer_id' => null,
                'reviewed_at' => null,
                'review_note' => '',
            ],
        );
    }
}
