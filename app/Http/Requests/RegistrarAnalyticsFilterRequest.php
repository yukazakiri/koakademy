<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\StudentType;
use App\Models\Course;
use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class RegistrarAnalyticsFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_year' => ['nullable', 'string', 'regex:/^\\d{4} - \\d{4}$/'],
            'semester' => ['nullable', 'integer', Rule::in([1, 2, 3])],
            'department_id' => ['nullable', 'integer'],
            'course_id' => ['nullable', 'integer'],
            'academic_year' => ['nullable', 'integer', 'between:1,7'],
            'gender' => ['nullable', 'string', Rule::in(['male', 'female', 'unspecified'])],
            'student_type' => ['nullable', Rule::enum(StudentType::class)],
            'intake_category' => ['nullable', Rule::in(['new_freshman', 'continuing_first_year', 'unclassified'])],
            'status' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $departmentId = $this->integer('department_id') ?: null;
            $courseId = $this->integer('course_id') ?: null;

            if ($departmentId && ! Department::query()->whereKey($departmentId)->exists()) {
                $validator->errors()->add('department_id', 'The selected department is not available for this institution.');
            }

            if ($courseId) {
                $course = Course::query()->select(['id', 'department_id'])->find($courseId);
                if (! $course) {
                    $validator->errors()->add('course_id', 'The selected program is not available for this institution.');
                } elseif ($departmentId && $course->department_id !== $departmentId) {
                    $validator->errors()->add('course_id', 'The selected program does not belong to the selected department.');
                }
            }
        }];
    }

    /** @return array<string, int|string|null> */
    public function filters(): array
    {
        return $this->safe()->only([
            'school_year', 'semester', 'department_id', 'course_id', 'academic_year',
            'gender', 'student_type', 'intake_category', 'status',
        ]);
    }
}
