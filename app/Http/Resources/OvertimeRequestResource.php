<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OvertimeRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $employee = $this->relationLoaded('employee') ? $this->employee : null;
        $reviewer = $this->relationLoaded('reviewer') ? $this->reviewer : null;

        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee_name' => $employee?->name,
            'nip' => (string) ($employee?->nip ?? ''),
            'team' => (string) ($employee?->team ?? ''),
            'date' => $this->date?->toDateString(),
            'start_at' => $this->start_at?->toIso8601String(),
            'end_at' => $this->end_at?->toIso8601String(),
            'minutes' => $this->minutes,
            'reason' => $this->reason,
            'status' => $this->status,
            'reviewer_id' => $this->reviewer_id,
            'reviewer_name' => $reviewer?->name,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'review_note' => (string) ($this->review_note ?? ''),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
