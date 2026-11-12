<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:120'],
            'is_national' => ['nullable', 'boolean'],
            'office_location_id' => ['nullable', 'integer', 'exists:office_locations,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Tanggal libur wajib diisi.',
            'name.required' => 'Nama hari libur wajib diisi.',
        ];
    }
}
