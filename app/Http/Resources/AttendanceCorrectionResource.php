<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceCorrectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $employee = $this->relationLoaded('employee') ? $this->employee : null;
        $reviewer = $this->relationLoaded('reviewer') ? $this->reviewer : null;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'employee_id' => $this->employee_id,
            'employee_name' => $employee?->name,
            'nip' => (string) ($employee?->nip ?? ''),
            'team' => (string) ($employee?->team ?? ''),
            'attendance_id' => $this->attendance_id,
            'date' => $this->date?->toDateString(),
            'scheduled_start' => $this->scheduled_start,
            'scheduled_end' => $this->scheduled_end,
            'recorded_check_in_at' => $this->recorded_check_in?->toIso8601String(),
            'recorded_check_out_at' => $this->recorded_check_out?->toIso8601String(),
            'proposed_check_in_at' => $this->proposed_check_in?->toIso8601String(),
            'proposed_check_out_at' => $this->proposed_check_out?->toIso8601String(),
            'correction_type' => $this->correction_type,
            'reason' => $this->reason,
            'evidence_url' => $this->evidence_url,
            'evidence_name' => $this->evidence_name,
            'evidence_meta' => $this->evidence_meta,
            'status' => $this->status,
            'reviewed_by' => $this->reviewer_id,
            'reviewer_id' => $this->reviewer_id,
            'reviewer_name' => $reviewer?->name,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'review_note' => (string) ($this->review_note ?? ''),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
