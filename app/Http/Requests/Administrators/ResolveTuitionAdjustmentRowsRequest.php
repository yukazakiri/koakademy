<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use Illuminate\Foundation\Http\FormRequest;

final class ResolveTuitionAdjustmentRowsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view_tuition_fees') ?? false;
    }

    public function rules(): array
    {
        return [
            'school_year' => ['required', 'string', 'max:30'],
            'semester' => ['required', 'integer', 'in:1,2'],
            'rows' => ['required', 'array', 'min:1', 'max:250'],
            'rows.*.client_row_id' => ['required', 'string', 'max:100'],
            'rows.*.identifier' => ['required', 'string', 'max:255'],
            'rows.*.total_fees' => ['nullable', 'numeric', 'min:0'],
            'rows.*.opening_paid' => ['nullable', 'numeric', 'min:0'],
            'rows.*.balance' => ['nullable', 'numeric'],
            'rows.*.prelim' => ['nullable', 'numeric', 'min:0'],
            'rows.*.midterm' => ['nullable', 'numeric', 'min:0'],
            'rows.*.finals' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
