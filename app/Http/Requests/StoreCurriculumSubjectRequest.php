<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\SubjectEnrolledEnum;
use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCurriculumSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->isAdministrative() ?? false;
    }

    public function rules(): array
    {
        $courseId = $this->courseId();

        return [
            'code' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'classification' => ['required', Rule::enum(SubjectEnrolledEnum::class)],
            'units' => ['required', 'integer', 'min:0'],
            'lecture' => ['nullable', 'integer', 'min:0'],
            'laboratory' => ['nullable', 'integer', 'min:0'],
            'academic_year' => ['nullable', 'integer', 'between:1,4'],
            'semester' => ['nullable', 'integer', 'between:1,3'],
            'group' => ['nullable', 'string', 'max:255'],
            'is_credited' => ['boolean'],
            'pre_riquisite' => ['nullable', 'array'],
            'pre_riquisite.*' => [
                'integer',
                Rule::exists('subject', 'id')->where('course_id', $courseId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Subject code is required.',
            'title.required' => 'Subject title is required.',
            'classification.required' => 'Classification is required.',
            'units.required' => 'Units are required.',
            'units.integer' => 'Units must be a whole number.',
            'pre_riquisite.*.exists' => 'Prerequisites must belong to this program.',
        ];
    }

    private function courseId(): int
    {
        $course = $this->route('course');

        if ($course instanceof Course) {
            return $course->id;
        }

        return (int) $course;
    }
}
