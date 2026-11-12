<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayrollSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'overtime_enabled' => ['sometimes', 'required', 'boolean'],
            'overtime_min_minutes' => ['sometimes', 'required', 'integer', 'min:0', 'max:240'],
            'ptkp' => ['sometimes', 'required', 'array'],
            'ptkp.*' => ['integer', 'min:0'],
            'pph21_brackets' => ['sometimes', 'required', 'array'],
            'pph21_brackets.*.limit' => ['nullable', 'integer', 'min:0'],
            'pph21_brackets.*.rate' => ['required_with:pph21_brackets', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
