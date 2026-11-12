<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:180',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'password' => ['nullable', 'string', 'min:6', 'max:100'],
            'role' => ['sometimes', Rule::in([
                User::ROLE_ADMIN,
                User::ROLE_SUPERVISOR,
                User::ROLE_EMPLOYEE,
            ])],
            'nip' => ['nullable', 'string', 'max:32'],
            'position' => ['nullable', 'string', 'max:120'],
            'team' => ['nullable', 'string', 'max:120'],
            'office_location_id' => ['nullable', 'integer', 'exists:office_locations,id'],
            'phone' => ['nullable', 'string', 'max:32'],
            'avatar_url' => ['nullable', 'string', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
            'joined_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Email sudah terdaftar.',
            'password.min' => 'Password minimal 6 karakter.',
            'role.in' => 'Peran tidak valid.',
        ];
    }
}
