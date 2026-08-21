<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateStudentProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStudentRole() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $studentId = Student::query()
            ->where('user_id', $this->user()?->id)
            ->value('id');
        $incomeModes = array_keys(config('income_brackets.modes', []));
        $selectedMode = (string) $this->input('income_bracket_mode', config('income_brackets.default_mode', 'annual'));
        $incomeBracketKeys = $this->incomeBracketKeysForMode($selectedMode);

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('students')->ignore($studentId),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:2000'],
            'civil_status' => ['nullable', 'string', 'max:50'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'religion' => ['nullable', 'string', 'max:100'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:male,female,other,prefer_not_to_say'],
            'contacts.emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'contacts.emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'contacts.emergency_contact_relationship' => ['nullable', 'string', 'max:255'],
            'contacts.facebook' => ['nullable', 'string', 'max:255'],
            'contacts.twitter' => ['nullable', 'string', 'max:255'],
            'contacts.instagram' => ['nullable', 'string', 'max:255'],
            'contacts.linkedin' => ['nullable', 'string', 'max:255'],
            'contacts.personal_contact' => ['nullable', 'string', 'max:20'],
            'education.elementary_school' => ['nullable', 'string', 'max:255'],
            'education.elementary_year_graduated' => ['nullable', 'string', 'max:20'],
            'education.high_school' => ['nullable', 'string', 'max:255'],
            'education.high_school_year_graduated' => ['nullable', 'string', 'max:20'],
            'education.senior_high_school' => ['nullable', 'string', 'max:255'],
            'education.senior_high_year_graduated' => ['nullable', 'string', 'max:20'],
            'education.college_school' => ['nullable', 'string', 'max:255'],
            'education.college_course' => ['nullable', 'string', 'max:255'],
            'education.college_year_graduated' => ['nullable', 'string', 'max:20'],
            'education.vocational_school' => ['nullable', 'string', 'max:255'],
            'education.vocational_course' => ['nullable', 'string', 'max:255'],
            'education.vocational_year_graduated' => ['nullable', 'string', 'max:20'],
            'parents.father_name' => ['nullable', 'string', 'max:255'],
            'parents.father_occupation' => ['nullable', 'string', 'max:100'],
            'parents.father_contact' => ['nullable', 'string', 'max:30'],
            'parents.father_email' => ['nullable', 'email', 'max:255'],
            'parents.mother_name' => ['nullable', 'string', 'max:255'],
            'parents.mother_occupation' => ['nullable', 'string', 'max:100'],
            'parents.mother_contact' => ['nullable', 'string', 'max:30'],
            'parents.mother_email' => ['nullable', 'email', 'max:255'],
            'parents.guardian_name' => ['nullable', 'string', 'max:255'],
            'parents.guardian_relationship' => ['nullable', 'string', 'max:100'],
            'parents.guardian_contact' => ['nullable', 'string', 'max:30'],
            'parents.guardian_email' => ['nullable', 'email', 'max:255'],
            'parents.family_address' => ['nullable', 'string', 'max:2000'],
            'personal_info.birthplace' => ['nullable', 'string', 'max:255'],
            'personal_info.citizenship' => ['nullable', 'string', 'max:100'],
            'personal_info.weight' => ['nullable', 'numeric', 'between:1,999.99'],
            'personal_info.height' => ['nullable', 'numeric', 'between:1,999.99'],
            'personal_info.current_address' => ['nullable', 'string', 'max:2000'],
            'personal_info.permanent_address' => ['nullable', 'string', 'max:2000'],
            'ethnicity' => ['nullable', 'string', 'max:100'],
            'city_of_origin' => ['nullable', 'string', 'max:100'],
            'province_of_origin' => ['nullable', 'string', 'max:100'],
            'region_of_origin' => ['nullable', 'string', 'max:100'],
            'is_indigenous_person' => ['nullable', 'boolean'],
            'indigenous_group' => ['nullable', 'required_if:is_indigenous_person,true,1', 'string', 'max:100'],
            'is_pwd' => ['nullable', 'boolean'],
            'pwd_type' => ['nullable', 'required_if:is_pwd,true,1', 'string', 'max:100'],
            'is_solo_parent' => ['nullable', 'boolean'],
            'is_senior_citizen' => ['nullable', 'boolean'],
            'is_magna_carta' => ['nullable', 'boolean'],
            'is_underprivileged' => ['nullable', 'boolean'],
            'is_first_generation' => ['nullable', 'boolean'],
            'income_bracket_mode' => ['nullable', 'string', 'in:'.implode(',', $incomeModes)],
            'use_same_parent_income' => ['nullable', 'boolean'],
            'family_income_bracket' => ['nullable', 'required_if:use_same_parent_income,true,1', 'string', 'in:'.implode(',', $incomeBracketKeys)],
            'father_income_bracket' => ['nullable', 'required_if:use_same_parent_income,false,0', 'string', 'in:'.implode(',', $incomeBracketKeys)],
            'mother_income_bracket' => ['nullable', 'required_if:use_same_parent_income,false,0', 'string', 'in:'.implode(',', $incomeBracketKeys)],
            'reporting_confirmed' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'gender' => $this->normalizeGender($this->input('gender')),
        ]);
    }

    private function normalizeGender(mixed $gender): ?string
    {
        if ($gender === null || $gender === '') {
            return null;
        }

        $normalizedGender = str_replace([' ', '-'], '_', mb_strtolower(mb_trim((string) $gender)));

        return match ($normalizedGender) {
            'male', 'female', 'other', 'prefer_not_to_say' => $normalizedGender,
            default => (string) $gender,
        };
    }

    /**
     * @return list<string>
     */
    private function incomeBracketKeysForMode(string $mode): array
    {
        $modes = config('income_brackets.modes', []);
        $modeConfig = is_array($modes) ? ($modes[$mode] ?? $modes[config('income_brackets.default_mode', 'annual')] ?? []) : [];

        return is_array($modeConfig['brackets'] ?? null) ? array_keys($modeConfig['brackets']) : [];
    }
}
