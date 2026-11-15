<?php

namespace Database\Seeders;

use App\Models\Holiday;
use App\Models\LeaveType;
use App\Models\OfficeLocation;
use App\Models\OfficeSetting;
use App\Models\PayrollDeductionRule;
use App\Models\PayrollSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkShift;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        TenantContext::clear();
        $this->call(SuperAdminSeeder::class);

        $tenant = Tenant::query()->updateOrCreate(
            ['id' => 1],
            [
                'name' => (string) config('absensi.tenant.name'),
                'code' => (string) config('absensi.tenant.code'),
                'plan' => 'basic',
                'status' => Tenant::STATUS_ACTIVE,
                'trial_ends_at' => null,
                'subscription_ends_at' => null,
                'max_employees' => (int) config('absensi.tenant.max_employees'),
            ],
        );

        TenantContext::set($tenant);
        OfficeSetting::current();

        $this->seedOfficeLocations();
        $this->seedAdmin();
        $this->seedLeaveTypes();
        $this->seedShifts();
        $this->seedPayroll();
        $this->seedHolidays();

        TenantContext::clear();
    }

    private function seedOfficeLocations(): void
    {
        OfficeLocation::query()->updateOrCreate(
            ['name' => 'Kantor Pusat'],
            [
                'address' => '',
                'latitude' => -6.2000000,
                'longitude' => 106.8166660,
                'radius_meters' => 100,
                'timezone' => 'Asia/Jakarta',
                'is_default' => true,
                'is_active' => true,
            ],
        );
    }

    private function seedAdmin(): void
    {
        $email = mb_strtolower((string) config('absensi.admin.email'));
        $password = (string) config('absensi.admin.password');

        if ($password === '') {
            if (app()->environment('production')) {
                throw new RuntimeException('ADMIN_PASSWORD wajib diisi di .env sebelum menjalankan seeder produksi.');
            }

            $password = 'admin123';
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => (string) config('absensi.admin.name'),
                'password' => $password,
                'role' => User::ROLE_ADMIN,
                'nip' => '',
                'position' => 'Administrator',
                'team' => '',
                'office_location_id' => OfficeLocation::defaultLocation()?->id,
                'is_active' => true,
                'joined_at' => now()->toDateString(),
            ],
        );
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
            $existing = Holiday::query()
                ->whereDate('date', $holiday['date'])
                ->whereNull('office_location_id')
                ->first();

            if ($existing !== null) {
                $existing->update(['name' => $holiday['name'], 'is_national' => true]);

                continue;
            }

            Holiday::query()->create([
                'date' => $holiday['date'],
                'name' => $holiday['name'],
                'is_national' => true,
                'office_location_id' => null,
            ]);
        }
    }
}
