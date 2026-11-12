<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use Illuminate\Database\Eloquent\Model;

class PayrollDeductionRule extends Model
{
    use BelongsToTenant;

    public const BASES = [
        'late_per_occurrence',
        'late_per_minute',
        'absence_per_day',
        'absence_daily_wage',
        'unpaid_leave_per_day',
        'unpaid_leave_daily_wage',
        'percent_base',
        'percent_gross',
        'fixed_monthly',
        'pph21',
    ];

    protected $fillable = [
        'name',
        'basis',
        'rate',
        'is_system',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'float',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
