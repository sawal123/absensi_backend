<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReplaceSalaryAllowancesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:users,id'],
            'allowances' => ['present', 'array'],
            'allowances.*.name' => ['required', 'string', 'max:120'],
            'allowances.*.amount' => ['required', 'integer', 'min:0'],
            'allowances.*.basis' => ['required', Rule::in(['monthly', 'per_present_day'])],
            'allowances.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'Karyawan wajib dipilih.',
            'allowances.*.name.required' => 'Nama tunjangan wajib diisi.',
            'allowances.*.amount.required' => 'Nominal tunjangan wajib diisi.',
            'allowances.*.basis.in' => 'Dasar tunjangan tidak valid.',
        ];
    }
}
