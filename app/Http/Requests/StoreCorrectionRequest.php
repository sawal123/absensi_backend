<?php

namespace App\Http\Requests;

use App\Models\AttendanceCorrection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attendance_id' => ['nullable', 'integer', 'exists:attendances,id'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'correction_type' => ['required', Rule::in(AttendanceCorrection::TYPES)],
            'proposed_check_in_at' => ['nullable', 'date'],
            'proposed_check_out_at' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:1000'],
            'evidence_url' => ['nullable', 'string', 'max:2048'],
            'evidence_name' => ['nullable', 'string', 'max:255'],
            'evidence_meta' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('proposed_check_in_at') || $this->filled('proposed_check_out_at')) {
                return;
            }
            $validator->errors()->add(
                'proposed_check_in_at',
                'Minimal satu jam masuk atau jam pulang usulan harus diisi.',
            );
        });
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Tanggal absensi wajib diisi.',
            'date.before_or_equal' => 'Tanggal absensi tidak boleh di masa depan.',
            'correction_type.required' => 'Jenis perbaikan wajib dipilih.',
            'correction_type.in' => 'Jenis perbaikan tidak valid.',
            'reason.required' => 'Alasan perbaikan wajib diisi.',
        ];
    }
}
