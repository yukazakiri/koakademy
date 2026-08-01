<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\Course;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class GenerateBulkAssessmentsRequest extends FormRequest
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
            'course_id' => ['nullable', 'integer', Rule::exists('courses', 'id')],
            'year_level' => ['nullable', 'integer', Rule::in([1, 2, 3, 4, 5])],
            'student_limit' => ['nullable', 'integer', Rule::in((array) config('assessment-exports.student_limit_options'))],
            'include_deleted' => ['required', 'boolean'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $courseId = $this->integer('course_id');
            if ($courseId === 0) {
                return;
            }

            $schoolId = app(TenantContext::class)->getCurrentSchoolId();
            $belongsToSchool = $schoolId !== null && Course::withoutSchoolScope()
                ->whereKey($courseId)
                ->where('school_id', $schoolId)
                ->exists();

            if (! $belongsToSchool) {
                $validator->errors()->add('course_id', 'The selected course is not available in the active school.');
            }
        }];
    }
}
