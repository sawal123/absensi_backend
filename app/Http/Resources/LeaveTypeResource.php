<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_paid' => (bool) $this->is_paid,
            'max_days_per_year' => $this->max_days_per_year,
            'requires_attachment' => (bool) $this->requires_attachment,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
