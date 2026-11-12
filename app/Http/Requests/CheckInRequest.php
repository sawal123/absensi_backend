<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'photo' => ['nullable', 'string', 'max:2048'],
            'photo_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'is_mocked' => ['nullable', 'boolean'],
            'liveness_passed' => ['nullable', 'boolean'],
            'device_id' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.required' => 'Lokasi absen tidak terdeteksi.',
            'longitude.required' => 'Lokasi absen tidak terdeteksi.',
            'photo_file.mimes' => 'Foto harus berformat JPG, PNG, atau WEBP.',
            'photo_file.max' => 'Ukuran foto maksimal 10 MB.',
        ];
    }
}
