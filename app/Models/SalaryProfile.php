<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryProfile extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'employee_id',
        'base_salary',
        'overtime_rate',
        'ptkp_status',
        'npwp',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'integer',
            'overtime_rate' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
