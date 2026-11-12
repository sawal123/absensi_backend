<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'overtime_enabled' => (bool) $this->overtime_enabled,
            'overtime_min_minutes' => $this->overtime_min_minutes,
            'ptkp' => $this->ptkp,
            'pph21_brackets' => $this->pph21_brackets,
        ];
    }
}
