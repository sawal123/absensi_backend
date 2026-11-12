<?php

namespace App\Http\Requests;

use App\Models\PayrollDeductionRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeductionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'basis' => ['required', Rule::in(PayrollDeductionRule::BASES)],
            'rate' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama komponen wajib diisi.',
            'basis.required' => 'Dasar perhitungan wajib dipilih.',
            'basis.in' => 'Dasar perhitungan tidak valid.',
            'rate.required' => 'Nilai potongan wajib diisi.',
        ];
    }
}
