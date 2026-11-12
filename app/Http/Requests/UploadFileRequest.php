<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'folder' => ['nullable', 'string', 'max:60', 'alpha_dash'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Berkas wajib dipilih.',
            'file.mimes' => 'Berkas harus berupa JPG, PNG, WEBP, atau PDF.',
            'file.max' => 'Ukuran berkas maksimal 10 MB.',
        ];
    }
}
