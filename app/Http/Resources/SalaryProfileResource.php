<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalaryProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $employee = $this->relationLoaded('employee') ? $this->employee : null;

        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee_name' => $employee?->name,
            'nip' => (string) ($employee?->nip ?? ''),
            'position' => (string) ($employee?->position ?? ''),
            'base_salary' => $this->base_salary,
            'overtime_rate' => $this->overtime_rate,
            'ptkp_status' => $this->ptkp_status,
            'npwp' => (string) ($this->npwp ?? ''),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
