<?php

namespace App\Support;

use App\Models\LeaveBalance;
use App\Models\LeaveType;

final class LeaveBalances
{
    /**
     * Pastikan setiap jenis cuti aktif punya baris saldo untuk tahun tertentu.
     */
    public static function ensure(int $employeeId, int $year): void
    {
        $types = LeaveType::query()->where('is_active', true)->get();

        foreach ($types as $type) {
            LeaveBalance::query()->firstOrCreate(
                [
                    'employee_id' => $employeeId,
                    'leave_type_id' => $type->id,
                    'year' => $year,
                ],
                [
                    'entitled_days' => $type->max_days_per_year,
                    'used_days' => 0,
                    'adjustment_days' => 0,
                ],
            );
        }
    }

    public static function for(int $employeeId, int $leaveTypeId, int $year): LeaveBalance
    {
        self::ensure($employeeId, $year);

        return LeaveBalance::query()->firstOrCreate(
            [
                'employee_id' => $employeeId,
                'leave_type_id' => $leaveTypeId,
                'year' => $year,
            ],
            ['entitled_days' => 0, 'used_days' => 0, 'adjustment_days' => 0],
        );
    }
}
