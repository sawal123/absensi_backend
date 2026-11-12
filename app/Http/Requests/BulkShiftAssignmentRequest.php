<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkShiftAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:users,id'],
            'shift_id' => ['nullable', 'integer', 'exists:work_shifts,id'],
            'dates' => ['nullable', 'array'],
            'dates.*' => ['date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $hasDates = is_array($this->input('dates')) && count($this->input('dates')) > 0;
            if ($hasDates) {
                return;
            }
            if ($this->filled('from') && $this->filled('to')) {
                return;
            }
            $validator->errors()->add('dates', 'Isi daftar tanggal atau rentang from/to.');
        });
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'Karyawan wajib dipilih.',
            'employee_id.exists' => 'Karyawan tidak ditemukan.',
            'shift_id.exists' => 'Shift tidak ditemukan.',
            'to.after_or_equal' => 'Tanggal akhir harus setelah tanggal mulai.',
        ];
    }
}
