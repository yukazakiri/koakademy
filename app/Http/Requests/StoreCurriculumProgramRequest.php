<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCurriculumProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->isAdministrative() ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'course_type_id' => ['required', 'integer', 'exists:course_types,id'],
            'lec_per_unit' => ['nullable', 'numeric', 'min:0'],
            'lab_per_unit' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
            'curriculum_year' => ['nullable', 'string', 'max:255'],
            'miscelaneous' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Program code is required.',
            'title.required' => 'Program title is required.',
            'department_id.required' => 'Department is required.',
            'department_id.exists' => 'The selected department does not exist.',
            'course_type_id.required' => 'Course Type is required.',
            'course_type_id.exists' => 'The selected course type does not exist.',
            'lec_per_unit.numeric' => 'Lecture per unit must be a number.',
            'lab_per_unit.numeric' => 'Lab per unit must be a number.',
            'miscelaneous.numeric' => 'Miscellaneous fee must be a number.',
        ];
    }
}
