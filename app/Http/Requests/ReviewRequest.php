<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'review_note' => ['nullable', 'string', 'max:1000', 'required_if:status,rejected'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status keputusan wajib diisi.',
            'status.in' => 'Status keputusan tidak valid.',
            'review_note.required_if' => 'Catatan wajib diisi saat menolak pengajuan.',
        ];
    }
}
