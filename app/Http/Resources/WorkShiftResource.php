<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'start' => $this->start_time,
            'end' => $this->end_time,
            'office' => $this->office,
            'break_label' => $this->break_label,
            'cross_day' => (bool) $this->cross_day,
            'is_active' => (bool) $this->is_active,
            'color' => $this->color,
            'duration_minutes' => $this->durationMinutes(),
        ];
    }
}
