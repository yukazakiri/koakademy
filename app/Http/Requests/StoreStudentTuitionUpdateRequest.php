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
        return [
            'school_year' => ['required', 'string', 'max:30'],
            'semester' => ['required', 'integer', Rule::in([1, 2])],
            'concern_type' => ['required', 'string', Rule::in(StudentTuitionUpdateRequest::concernTypes())],
            'receipt_number' => [
                Rule::requiredIf($this->input('concern_type') === StudentTuitionUpdateRequest::ConcernMissingPayment),
                'nullable', 'string', 'max:255',
            ],
            'details' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
