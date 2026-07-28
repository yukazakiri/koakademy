<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ShowEnrollmentPolicyContextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'student_type' => ['required', 'string', 'in:college,tesda'],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'academic_year' => ['nullable', 'integer', 'between:1,12'],
        ];
    }
}
