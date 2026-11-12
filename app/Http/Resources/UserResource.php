<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $office = $this->relationLoaded('officeLocation') ? $this->officeLocation : null;
        $tenant = $this->relationLoaded('tenant') ? $this->tenant : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'is_super_admin' => $this->isSuperAdmin(),
            'tenant_id' => $this->tenant_id,
            'tenant_name' => $tenant?->name,
            'tenant_code' => $tenant?->code,
            'tenant_status' => $tenant?->status,
            'tenant_is_active' => $tenant?->isActive(),
            'nip' => (string) ($this->nip ?? ''),
            'position' => (string) ($this->position ?? ''),
            'team' => (string) ($this->team ?? ''),
            'office_location_id' => $this->office_location_id,
            'office_location' => $office?->name,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'face_enrolled' => $this->hasEnrolledFace(),
            'face_enrolled_at' => $this->face_enrolled_at?->toIso8601String(),
            'initials' => $this->initials(),
            'is_active' => (bool) $this->is_active,
            'joined_at' => $this->joined_at?->toDateString(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
