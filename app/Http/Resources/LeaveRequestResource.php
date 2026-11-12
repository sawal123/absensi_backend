<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $employee = $this->relationLoaded('employee') ? $this->employee : null;
        $leaveType = $this->relationLoaded('leaveType') ? $this->leaveType : null;
        $reviewer = $this->relationLoaded('reviewer') ? $this->reviewer : null;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'employee_id' => $this->employee_id,
            'employee_name' => $employee?->name,
            'nip' => (string) ($employee?->nip ?? ''),
            'team' => (string) ($employee?->team ?? ''),
            'leave_type_id' => $this->leave_type_id,
            'leave_type_name' => $leaveType?->name,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'requested_days' => $this->working_days,
            'working_days' => $this->working_days,
            'reason' => $this->reason,
            'attachment_url' => $this->attachment_url,
            'status' => $this->status,
            'reviewed_by' => $this->reviewer_id,
            'reviewer_id' => $this->reviewer_id,
            'reviewer_name' => $reviewer?->name,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'review_note' => (string) ($this->review_note ?? ''),
            'created_at' => $this->created_at?->toIso8601String(),
            'balance_before' => $this->balance_before,
            'balance_after' => $this->balance_after,
            'team_available' => $this->team_available,
            'team_total' => $this->team_total,
        ];
    }
}
