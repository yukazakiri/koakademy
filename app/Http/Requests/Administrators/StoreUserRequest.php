<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // auth middleware handles this
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'role' => ['required', new Enum(UserRole::class)],
            'school_id' => ['nullable', 'exists:schools,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'faculty_id_number' => ['nullable', 'string', 'max:255'],
            'record_id' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'avatar_url' => ['nullable'], // simplified for now
            'theme_color' => ['nullable', 'string', 'max:255'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,id'],
        ];
    }
}
