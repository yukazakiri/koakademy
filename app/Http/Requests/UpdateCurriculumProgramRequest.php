<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateCurriculumProgramRequest extends FormRequest
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
            'lec_per_unit.numeric' => 'Lecture per unit must be a number.',
            'lab_per_unit.numeric' => 'Lab per unit must be a number.',
            'miscelaneous.numeric' => 'Miscellaneous fee must be a number.',
        ];
    }
}
