<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\ChedProgramRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCurriculumProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->isAdministrative() ?? false;
    }

    public function rules(): array
    {
        $kind = $this->input('curriculum_kind', 'program');
        $isDiploma = $kind === 'tesda_qualification' && $this->input('tesda_program_type') === 'diploma';

        return [
            'code' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'department_id' => [Rule::requiredIf($kind === 'program'), 'nullable', 'integer', 'exists:departments,id'],
            'course_type_id' => [Rule::requiredIf($kind === 'program'), 'nullable', 'integer', 'exists:course_types,id'],
            'curriculum_kind' => ['required', Rule::in(['program', 'tesda_qualification', 'grade_pathway', 'senior_high_pathway', 'legacy'])],
            'curriculum_stage' => [Rule::requiredIf(in_array($kind, ['grade_pathway', 'senior_high_pathway'], true)), 'nullable', 'string', 'max:50'],
            'duration_hours' => [$isDiploma ? 'required' : 'nullable', 'integer', 'min:1', 'max:65535'],
            'qualification_level' => ['nullable', 'string', 'max:50'],
            'tesda_program_type' => ['nullable', Rule::in(['national_certificate', 'diploma'])],
            'duration_years' => [$isDiploma ? 'required' : 'nullable', 'numeric', 'min:0.5', 'max:10'],
            'internship_hours' => [$isDiploma ? 'required' : 'nullable', 'integer', 'min:0', 'max:65535'],
            'bundled_qualifications' => [
                $isDiploma ? 'required' : 'nullable',
                'array',
                ...($isDiploma ? ['min:1'] : []),
            ],
            'bundled_qualifications.*' => ['string', 'max:150'],
            'advanced_topics' => [$isDiploma ? 'required' : 'nullable', 'string', 'max:5000'],
            'catalog_reference' => ['nullable', 'string', 'max:255'],
            'lec_per_unit' => ['nullable', 'numeric', 'min:0'],
            'lab_per_unit' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
            'curriculum_year' => ['nullable', 'string', 'max:255'],
            'miscelaneous' => ['nullable', 'numeric', 'min:0'],
            ...ChedProgramRules::validationRules(),
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

    protected function prepareForValidation(): void
    {
        $bundledQualifications = $this->input('bundled_qualifications');

        if (is_string($bundledQualifications)) {
            $bundledQualifications = array_values(array_unique(array_filter(
                array_map(trim(...), preg_split('/[\r\n,]+/', $bundledQualifications) ?: []),
                static fn (string $qualification): bool => $qualification !== '',
            )));
        }

        $this->merge([
            'curriculum_kind' => $this->input('curriculum_kind', 'program'),
            'bundled_qualifications' => $bundledQualifications,
        ]);
    }
}
