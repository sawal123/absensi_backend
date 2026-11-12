<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOvertimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Tanggal lembur wajib diisi.',
            'start_at.required' => 'Jam mulai lembur wajib diisi.',
            'end_at.required' => 'Jam selesai lembur wajib diisi.',
            'end_at.after' => 'Jam selesai harus setelah jam mulai.',
            'reason.required' => 'Alasan lembur wajib diisi.',
        ];
    }
}
