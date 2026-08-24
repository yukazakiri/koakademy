<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\GeneralSetting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class StoreFacultyCustomFieldDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('updateFacultyFields', GeneralSetting::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/'],
            'field_type' => ['required', Rule::in(['text', 'date', 'number', 'select'])],
            'help_text' => ['nullable', 'string', 'max:2000'],
            'options' => ['nullable', 'array', 'max:100'],
            'options.*' => ['required', 'string', 'max:255', 'distinct'],
            'source_header_aliases' => ['nullable', 'array', 'max:100'],
            'source_header_aliases.*' => ['required', 'string', 'max:255', 'distinct'],
            'is_required' => ['required', 'boolean'],
            'is_sensitive' => ['required', 'boolean'],
            'display_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if ($this->input('field_type') === 'select' && $this->collect('options')->filter()->isEmpty()) {
                $validator->errors()->add('options', 'Select fields need at least one allowed option.');
            }
        }];
    }
}
