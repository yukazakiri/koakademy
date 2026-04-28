<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Http\Requests\Administrators;

use Illuminate\Foundation\Http\FormRequest;

final class LibraryAuthorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'biography' => ['nullable', 'string'],
            'birth_date' => ['nullable', 'date'],
            'nationality' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Author name is required.',
            'birth_date.date' => 'Birth date must be a valid date.',
        ];
    }
}
