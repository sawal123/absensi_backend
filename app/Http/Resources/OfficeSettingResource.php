<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfficeSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'office_name' => $this->office_name,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'radius_meters' => $this->radius_meters,
            'work_start' => $this->work_start,
            'work_end' => $this->work_end,
            'early_check_in_minutes' => $this->early_check_in_minutes,
            'late_tolerance_minutes' => $this->late_tolerance_minutes,
            'face_match_required' => (bool) $this->face_match_required,
            'face_match_threshold' => $this->face_match_threshold,
            'overtime_requires_approval' => (bool) $this->overtime_requires_approval,
            'device_binding_enabled' => (bool) $this->device_binding_enabled,
            'selfie_required' => (bool) $this->selfie_required,
            'mock_location_blocked' => (bool) $this->mock_location_blocked,
            'timezone' => $this->timezone,
        ];
    }
}
