<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\Faculty;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class ConfirmFacultyBulkImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Faculty::class) || Gate::allows('update', Faculty::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'row_ids' => ['required', 'array', 'min:1', 'max:10000'],
            'row_ids.*' => ['required', 'integer', 'distinct'],
            'create_custom_field_keys' => ['nullable', 'array', 'max:100'],
            'create_custom_field_keys.*' => ['required', 'string', 'max:100', 'distinct', 'regex:/^[a-z][a-z0-9_]*$/'],
        ];
    }
}
