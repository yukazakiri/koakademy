<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\IndustryCourseCode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class ConfirmCodeAuthorityImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', IndustryCourseCode::class)
            || Gate::allows('update', IndustryCourseCode::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'row_ids' => ['required', 'array', 'min:1', 'max:10000'],
            'row_ids.*' => ['required', 'integer', 'distinct'],
            'adopt_column_keys' => ['nullable', 'array', 'max:100'],
            'adopt_column_keys.*' => ['required', 'string', 'max:100', 'distinct', 'regex:/^[a-z][a-z0-9_]*$/'],
        ];
    }
}
