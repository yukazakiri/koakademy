<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\CodeAuthority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class UpdateCodeAuthorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', CodeAuthority::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'size:2', 'regex:/^[a-zA-Z]{2}$/'],
            'curriculum_framework' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
