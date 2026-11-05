<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use Illuminate\Foundation\Http\FormRequest;

final class ResolveStudentTuitionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage_tuition_fees') ?? false;
    }

    public function rules(): array
    {
        return [
            'resolution_note' => ['required', 'string', 'min:5', 'max:2000'],
            'transaction_id' => ['nullable', 'integer', 'exists:transactions,id'],
            'tuition_adjustment_id' => ['nullable', 'integer', 'exists:tuition_adjustments,id'],
        ];
    }
}
