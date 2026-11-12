<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOfficeSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'office_location_id' => ['nullable', 'integer', 'exists:office_locations,id'],
            'office_name' => ['sometimes', 'required', 'string', 'max:120'],
            'latitude' => ['sometimes', 'required', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['sometimes', 'required', 'integer', 'min:10', 'max:5000'],
            'work_start' => ['sometimes', 'required', 'date_format:H:i'],
            'work_end' => ['sometimes', 'required', 'date_format:H:i'],
            'early_check_in_minutes' => ['sometimes', 'required', 'integer', 'min:0', 'max:240'],
            'late_tolerance_minutes' => ['sometimes', 'required', 'integer', 'min:0', 'max:120'],
            'face_match_required' => ['sometimes', 'required', 'boolean'],
            'face_match_threshold' => ['sometimes', 'required', 'integer', 'min:0', 'max:64'],
            'overtime_requires_approval' => ['sometimes', 'required', 'boolean'],
            'device_binding_enabled' => ['sometimes', 'required', 'boolean'],
            'selfie_required' => ['sometimes', 'required', 'boolean'],
            'mock_location_blocked' => ['sometimes', 'required', 'boolean'],
            'timezone' => ['sometimes', 'required', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.between' => 'Latitude tidak valid.',
            'longitude.between' => 'Longitude tidak valid.',
            'radius_meters.min' => 'Radius minimal 10 meter.',
            'work_start.date_format' => 'Jam kerja harus berformat HH:MM.',
            'work_end.date_format' => 'Jam kerja harus berformat HH:MM.',
            'face_match_threshold.max' => 'Ambang face match maksimal 64.',
        ];
    }
}
