<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->relationLoaded('user') ? $this->user : null;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'employee_name' => $user?->name,
            'nip' => (string) ($user?->nip ?? ''),
            'team' => (string) ($user?->team ?? ''),
            'date' => $this->date?->toDateString(),
            'check_in_at' => $this->check_in_at?->toIso8601String(),
            'check_in_lat' => $this->check_in_lat,
            'check_in_lng' => $this->check_in_lng,
            'check_in_photo' => $this->check_in_photo,
            'check_in_distance' => $this->check_in_distance,
            'check_out_at' => $this->check_out_at?->toIso8601String(),
            'check_out_lat' => $this->check_out_lat,
            'check_out_lng' => $this->check_out_lng,
            'check_out_photo' => $this->check_out_photo,
            'check_out_distance' => $this->check_out_distance,
            'is_mocked' => (bool) $this->is_mocked,
            'liveness_passed' => (bool) $this->liveness_passed,
            'face_match_score' => $this->face_match_score,
            'status' => $this->status,
            'note' => (string) ($this->note ?? ''),
            'work_duration_minutes' => $this->workDurationMinutes(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
