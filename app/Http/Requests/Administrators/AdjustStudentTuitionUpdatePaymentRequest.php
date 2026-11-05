<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use Illuminate\Foundation\Http\FormRequest;

final class AdjustStudentTuitionUpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage_tuition_fees') ?? false;
    }

    public function rules(): array
    {
        $hasAdjustments = is_array($this->input('adjustments')) && count($this->input('adjustments')) > 0;

        return [
            'transaction_id' => [$hasAdjustments ? 'nullable' : 'required', 'integer', 'exists:transactions,id'],
            'amount' => [$hasAdjustments ? 'nullable' : 'required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'adjustments' => ['nullable', 'array', 'max:10'],
            'adjustments.*.transaction_id' => ['required_with:adjustments', 'integer', 'exists:transactions,id'],
            'adjustments.*.amount' => ['required_with:adjustments', 'numeric', 'min:0.01', 'max:9999999.99'],
            'adjustments.*.reference_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}
