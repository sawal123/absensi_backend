<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'leave_type_id' => $this->leave_type_id,
            'leave_type_name' => $this->relationLoaded('leaveType') ? $this->leaveType?->name : null,
            'year' => $this->year,
            'entitled_days' => $this->entitled_days,
            'used_days' => $this->used_days,
            'adjustment_days' => $this->adjustment_days,
            'remaining_days' => $this->remaining_days,
        ];
    }
}
