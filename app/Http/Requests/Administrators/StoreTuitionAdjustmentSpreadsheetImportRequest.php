<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use Illuminate\Foundation\Http\FormRequest;

final class StoreTuitionAdjustmentSpreadsheetImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage_tuition_fees') ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
            'school_year' => ['required', 'string', 'max:30'],
            'semester' => ['required', 'integer', 'in:1,2'],
        ];
    }
}
