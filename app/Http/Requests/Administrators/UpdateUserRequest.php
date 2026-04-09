<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($this->route('user')),
            ],
            'role' => ['required', new Enum(UserRole::class)],
            'school_id' => ['nullable', 'exists:schools,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'faculty_id_number' => ['nullable', 'string', 'max:255'],
            'record_id' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'avatar_url' => ['nullable'],
            'theme_color' => ['nullable', 'string', 'max:255'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,id'],
        ];
    }
}
