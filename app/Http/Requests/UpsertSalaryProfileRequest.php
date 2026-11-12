<?php

namespace App\Http\Requests;

use App\Models\PayrollSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertSalaryProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:users,id'],
            'base_salary' => ['required', 'integer', 'min:0'],
            'overtime_rate' => ['nullable', 'integer', 'min:0'],
            'ptkp_status' => ['nullable', Rule::in(array_keys(PayrollSetting::DEFAULT_PTKP))],
            'npwp' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'Karyawan wajib dipilih.',
            'base_salary.required' => 'Gaji pokok wajib diisi.',
            'base_salary.min' => 'Gaji pokok tidak boleh negatif.',
            'ptkp_status.in' => 'Status PTKP tidak valid.',
        ];
    }
}
