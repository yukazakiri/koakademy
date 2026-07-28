<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\EnrollmentRequirement;
use App\Models\StudentEnrollment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReviewEnrollmentRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $enrollment = $this->route('enrollment');
        $requirement = $this->route('requirement');

        return $enrollment instanceof StudentEnrollment
            && $requirement instanceof EnrollmentRequirement
            && (int) $requirement->student_enrollment_id === (int) $enrollment->id
            && $this->user()?->can('update', $enrollment) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['verify', 'waive'])],
            'reason' => ['required_if:action,waive', 'nullable', 'string', 'max:2000'],
            'evidence_path' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'max:96'],
        ];
    }
}
