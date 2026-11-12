<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollSlip extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'employee_id',
        'period_year',
        'period_month',
        'employee_name',
        'nip',
        'position',
        'base_salary',
        'gross_earnings',
        'total_deductions',
        'net_pay',
        'breakdown',
        'status',
        'finalized_by',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'period_year' => 'integer',
            'period_month' => 'integer',
            'base_salary' => 'integer',
            'gross_earnings' => 'integer',
            'total_deductions' => 'integer',
            'net_pay' => 'integer',
            'breakdown' => 'array',
            'finalized_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
