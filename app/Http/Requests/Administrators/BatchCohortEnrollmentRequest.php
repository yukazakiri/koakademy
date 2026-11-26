<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class BatchCohortEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->canAccessAdminPortal();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'target_year_level' => ['required', 'integer', 'min:1', 'max:5'],
            'target_semester' => ['required', 'integer', 'in:1,2,3'],
            'school_year' => ['nullable', 'string', 'max:32'],
            'student_ids' => ['required', 'array', 'min:1', 'max:200'],
            'student_ids.*' => ['required', 'integer', 'exists:students,id'],
            'subject_ids' => ['required', 'array', 'min:1'],
            'subject_ids.*' => ['required', 'integer', 'exists:subject,id'],
            'block_section' => ['nullable', 'string', 'max:100'],
            'auto_assign_classes' => ['boolean'],
            'force_overload' => ['boolean'],
            'promote_student_year_level' => ['boolean'],
            'miscellaneous_fee' => ['nullable', 'numeric', 'min:0'],
            'downpayment' => ['nullable', 'numeric', 'min:0'],
            'override_window' => ['boolean'],
            'override_holds' => ['boolean'],
            'override_holds_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
