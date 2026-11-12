<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollSlipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $breakdown = $this->breakdown ?? [];

        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->employee_name,
            'nip' => (string) ($this->nip ?? ''),
            'position' => (string) ($this->position ?? ''),
            'period_year' => $this->period_year,
            'period_month' => $this->period_month,
            'year' => $this->period_year,
            'month' => $this->period_month,
            'base_salary' => $this->base_salary,
            'gross_earnings' => $this->gross_earnings,
            'total_deductions' => $this->total_deductions,
            'net_pay' => $this->net_pay,
            'earnings' => $breakdown['earnings'] ?? [],
            'deductions' => $breakdown['deductions'] ?? [],
            'metrics' => $breakdown['metrics'] ?? [],
            'breakdown' => $breakdown,
            'status' => $this->status,
            'finalized_by' => $this->finalized_by,
            'finalized_at' => $this->finalized_at?->toIso8601String(),
        ];
    }
}
