<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfficeLocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => (string) ($this->address ?? ''),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'radius_meters' => $this->radius_meters,
            'timezone' => $this->timezone,
            'is_default' => (bool) $this->is_default,
            'is_active' => (bool) $this->is_active,
            'employees_count' => $this->whenCounted('users'),
        ];
    }
}
