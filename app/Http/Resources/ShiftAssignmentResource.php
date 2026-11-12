<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'date' => $this->date?->toDateString(),
            'shift_id' => $this->shift_id,
            'shift' => $this->relationLoaded('shift')
                ? new WorkShiftResource($this->shift)
                : null,
        ];
    }
}
