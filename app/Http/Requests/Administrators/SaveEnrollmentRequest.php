<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class SaveEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->canAccessAdminPortal();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'exists:students,id'],
            'semester' => ['required', 'integer', 'in:1,2'],
            'academic_year' => ['required', 'integer', 'in:1,2,3,4,5'],
            'subjects' => ['required', 'array', 'min:1'],
            'subjects.*.subject_id' => ['required', 'exists:subject,id'],
            'subjects.*.class_id' => ['nullable', 'exists:classes,id'],
            'subjects.*.is_modular' => ['boolean'],
            'subjects.*.exclude_from_tuition' => ['boolean'],
            'subjects.*.lecture_fee' => ['required', 'numeric', 'min:0'],
            'subjects.*.laboratory_fee' => ['required', 'numeric', 'min:0'],
            'subjects.*.enrolled_lecture_units' => ['required', 'integer', 'min:0'],
            'subjects.*.enrolled_laboratory_units' => ['required', 'integer', 'min:0'],
            'discount' => ['nullable', 'integer', 'min:0', 'max:100'],
            'discount_id' => ['nullable', 'integer', 'exists:enrollment_discounts,id'],
            'miscellaneous_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'downpayment' => ['nullable', 'numeric', 'min:0'],
            'additional_fees' => ['nullable', 'array'],
            'additional_fees.*.fee_name' => ['required_with:additional_fees', 'string'],
            'additional_fees.*.amount' => ['required_with:additional_fees', 'numeric', 'min:0'],
            'notify_student' => ['nullable', 'boolean'],
            'change_reason' => ['nullable', 'string', 'max:1000'],
            'force_overload' => ['nullable', 'boolean'],
        ];
    }
}
