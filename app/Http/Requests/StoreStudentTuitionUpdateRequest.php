<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\StudentTuitionUpdateRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreStudentTuitionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $isPayment = $this->input('concern_type') === StudentTuitionUpdateRequest::ConcernMissingPayment;
        $hasReceiptsArray = is_array($this->input('receipts')) && count($this->input('receipts')) > 0;

        return [
            'school_year' => ['required', 'string', 'max:30'],
            'semester' => ['required', 'integer', Rule::in([1, 2])],
            'concern_type' => ['required', 'string', Rule::in(StudentTuitionUpdateRequest::concernTypes())],
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:9999999.99'],
            'receipt_number' => [
                Rule::excludeIf(! $isPayment && ! $hasReceiptsArray),
                Rule::requiredIf($isPayment && ! $hasReceiptsArray),
                'nullable', 'string', 'max:255',
            ],
            'receipt_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'receipts' => ['nullable', 'array', 'max:10'],
            'receipts.*.receipt_number' => ['nullable', 'string', 'max:255'],
            'receipts.*.amount' => ['nullable', 'numeric', 'min:0.01', 'max:9999999.99'],
            'receipts.*.image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'details' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
