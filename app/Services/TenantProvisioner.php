<?php

namespace App\Services;

use App\Models\LeaveType;
use App\Models\OfficeLocation;
use App\Models\OfficeSetting;
use App\Models\PayrollDeductionRule;
use App\Models\PayrollSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkShift;
use App\Support\TenantContext;

class TenantProvisioner
{
    /**
     * Buat data awal untuk tenant baru. Tidak ada pendaftaran publik; hanya
     * dipanggil dari endpoint platform super admin.
     *
     * @param  array{name: string, email: string, password: string}  $admin
     */
    public function provision(Tenant $tenant, array $admin): User
    {
        $previous = TenantContext::get();
        TenantContext::set($tenant);

        try {
            OfficeSetting::current();

            OfficeLocation::query()->create([
                'name' => 'Kantor Pusat',
                'address' => '',
                'latitude' => -6.2000000,
                'longitude' => 106.8166660,
                'radius_meters' => 100,
                'timezone' => 'Asia/Jakarta',
                'is_default' => true,
                'is_active' => true,
            ]);

            foreach ([
                ['name' => 'Cuti tahunan', 'is_paid' => true, 'max_days_per_year' => 12, 'requires_attachment' => false],
                ['name' => 'Izin', 'is_paid' => true, 'max_days_per_year' => 3, 'requires_attachment' => false],
                ['name' => 'Sakit', 'is_paid' => true, 'max_days_per_year' => 12, 'requires_attachment' => true],
            ] as $type) {
                LeaveType::query()->create($type + ['is_active' => true]);
            }

            PayrollSetting::current();

            foreach ([
                ['name' => 'Keterlambatan', 'basis' => 'late_per_occurrence', 'rate' => 25000, 'is_system' => false],
                ['name' => 'Absen tanpa keterangan', 'basis' => 'absence_daily_wage', 'rate' => 100, 'is_system' => false],
                ['name' => 'Cuti tidak dibayar', 'basis' => 'unpaid_leave_daily_wage', 'rate' => 100, 'is_system' => false],
                ['name' => 'BPJS Kesehatan', 'basis' => 'percent_gross', 'rate' => 1, 'is_system' => false],
                ['name' => 'BPJS JHT', 'basis' => 'percent_gross', 'rate' => 2, 'is_system' => false],
                ['name' => 'BPJS JP', 'basis' => 'percent_gross', 'rate' => 1, 'is_system' => false],
                ['name' => 'PPh21', 'basis' => 'pph21', 'rate' => 0, 'is_system' => true],
            ] as $index => $rule) {
                PayrollDeductionRule::query()->create($rule + [
                    'is_active' => true,
                    'sort_order' => $index,
                ]);
            }

            foreach ([
                ['name' => 'Reguler', 'start_time' => '08:00', 'end_time' => '17:00', 'break_label' => 'Istirahat 12.00 – 13.00', 'cross_day' => false, 'color' => 0xFF2563EB],
                ['name' => 'Pagi', 'start_time' => '06:00', 'end_time' => '14:00', 'break_label' => 'Istirahat 10.00 – 10.30', 'cross_day' => false, 'color' => 0xFFB45309],
                ['name' => 'Shift malam', 'start_time' => '22:00', 'end_time' => '06:00', 'break_label' => null, 'cross_day' => true, 'color' => 0xFF4F46E5],
            ] as $shift) {
                WorkShift::query()->create($shift + ['office' => 'Kantor Pusat', 'is_active' => true]);
            }

            return User::query()->create([
                'name' => $admin['name'],
                'email' => mb_strtolower($admin['email']),
                'password' => $admin['password'],
                'role' => User::ROLE_ADMIN,
                'position' => 'Administrator',
                'team' => 'Tim Operasional',
                'is_active' => true,
                'joined_at' => now()->toDateString(),
            ]);
        } finally {
            TenantContext::set($previous);
        }
    }
}
