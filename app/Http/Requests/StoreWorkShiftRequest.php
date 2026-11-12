<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'office' => ['nullable', 'string', 'max:120'],
            'break_label' => ['nullable', 'string', 'max:120'],
            'cross_day' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'color' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama shift wajib diisi.',
            'start_time.required' => 'Jam mulai wajib diisi.',
            'start_time.date_format' => 'Jam mulai harus berformat HH:MM.',
            'end_time.required' => 'Jam selesai wajib diisi.',
            'end_time.date_format' => 'Jam selesai harus berformat HH:MM.',
        ];
    }
}
