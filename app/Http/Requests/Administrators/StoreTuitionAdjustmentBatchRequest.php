<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use Illuminate\Foundation\Http\FormRequest;

final class StoreTuitionAdjustmentBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage_tuition_fees') ?? false;
    }

    public function rules(): array
    {
        return [
            'batch_key' => ['required', 'uuid'],
            'source' => ['nullable', 'string', 'in:workspace,clipboard,student'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'rows' => ['required', 'array', 'min:1', 'max:250'],
            'rows.*.client_row_id' => ['required', 'string', 'max:100'],
            'rows.*.enrollment_id' => ['required', 'integer', 'exists:student_enrollment,id'],
            'rows.*.tuition_id' => ['required', 'integer', 'exists:student_tuition,id'],
            'rows.*.state_hash' => ['required', 'string', 'size:64'],
            'rows.*.total_fees' => ['required', 'numeric', 'min:0'],
            'rows.*.opening_paid' => ['required', 'numeric', 'min:0'],
            'rows.*.balance' => ['required', 'numeric'],
            'rows.*.lecture' => ['nullable', 'numeric', 'min:0'],
            'rows.*.laboratory' => ['nullable', 'numeric', 'min:0'],
            'rows.*.miscellaneous' => ['nullable', 'numeric', 'min:0'],
            'rows.*.discount' => ['nullable', 'integer', 'min:0', 'max:100'],
            'rows.*.required_downpayment' => ['nullable', 'numeric', 'min:0'],
            'rows.*.installments' => ['nullable', 'array:prelim,midterm,finals'],
            'rows.*.installments.prelim' => ['required_with:rows.*.installments', 'numeric', 'min:0'],
            'rows.*.installments.midterm' => ['required_with:rows.*.installments', 'numeric', 'min:0'],
            'rows.*.installments.finals' => ['required_with:rows.*.installments', 'numeric', 'min:0'],
        ];
    }
}
