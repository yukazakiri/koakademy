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
            ...$this->chedRules(),
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

    /** @return array<string, array<int, mixed>> */
    private function chedRules(): array
    {
        return [
            'ched_major' => ['nullable', 'string', 'max:255'],
            'ched_has_thesis' => ['nullable', 'boolean'],
            'ched_program_status' => ['nullable', 'string', 'in:CO,PO,DO,NO,NA'],
            'ched_authority_category' => ['nullable', 'string', 'in:GP,GR,BR,OT'],
            'ched_authority_serial' => ['nullable', 'string', 'max:100'],
            'ched_authority_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'ched_authority_other_program' => ['nullable', 'string', 'max:255'],
            'ched_delivery_mode' => ['nullable', 'string', 'in:SE,TR,SD,TD,DE'],
            'ched_normal_length_years' => ['nullable', 'numeric', 'min:0'],
            'ched_program_credit_units' => ['nullable', 'integer', 'min:0'],
            'ched_tuition_per_unit' => ['nullable', 'numeric', 'min:0'],
            'ched_program_fee' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
