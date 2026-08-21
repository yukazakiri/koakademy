<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Student;

final class StudentProfileCompletionService
{
    /**
     * @return array{total: int, completed: int, percentage: int, missing: array<int, array{key: string, label: string, section: string, example?: string}>}
     */
    public function summarize(?Student $student): array
    {
        if (! $student instanceof Student) {
            return [
                'total' => 0,
                'completed' => 0,
                'percentage' => 0,
                'missing' => [],
            ];
        }

        $student->loadMissing(['studentContactsInfo', 'studentEducationInfo', 'studentParentInfo', 'personalInfo']);

        $fields = $this->fields($student);
        $missing = collect($fields)
            ->filter(fn (array $field): bool => ! $this->filled($field['value'] ?? null))
            ->map(fn (array $field): array => array_filter([
                'key' => $field['key'],
                'label' => $field['label'],
                'section' => $field['section'],
                'example' => $field['example'] ?? null,
            ], static fn (mixed $value): bool => $value !== null))
            ->values()
            ->all();

        $total = count($fields);
        $completed = $total - count($missing);

        return [
            'total' => $total,
            'completed' => $completed,
            'percentage' => $total === 0 ? 0 : (int) round(($completed / $total) * 100),
            'missing' => $missing,
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, section: string, value: mixed, example?: string}>
     */
    private function fields(Student $student): array
    {
        $contacts = $student->studentContactsInfo;
        $education = $student->studentEducationInfo;
        $parents = $student->studentParentInfo;

        $personalInfo = $student->personalInfo;
        $incomeValue = $student->use_same_parent_income
            ? $student->family_income_bracket
            : ($this->filled($student->father_income_bracket) && $this->filled($student->mother_income_bracket));

        return [
            ['key' => 'first_name', 'label' => 'First name', 'section' => 'personal', 'value' => $student->first_name, 'example' => 'Juan'],
            ['key' => 'last_name', 'label' => 'Last name', 'section' => 'personal', 'value' => $student->last_name, 'example' => 'Dela Cruz'],
            ['key' => 'email', 'label' => 'Student email', 'section' => 'personal', 'value' => $student->email, 'example' => 'juan@example.com'],
            ['key' => 'phone', 'label' => 'Phone number', 'section' => 'personal', 'value' => $student->phone, 'example' => '+63 912 345 6789'],
            ['key' => 'address', 'label' => 'Home address', 'section' => 'personal', 'value' => $student->address, 'example' => 'Davao City, Davao del Sur'],
            ['key' => 'birth_date', 'label' => 'Birth date', 'section' => 'personal', 'value' => $student->birth_date],
            ['key' => 'gender', 'label' => 'Gender', 'section' => 'personal', 'value' => $student->gender],
            ['key' => 'civil_status', 'label' => 'Civil status', 'section' => 'personal', 'value' => $student->civil_status],
            ['key' => 'nationality', 'label' => 'Nationality', 'section' => 'personal', 'value' => $student->nationality, 'example' => 'Filipino'],
            ['key' => 'religion', 'label' => 'Religion', 'section' => 'personal', 'value' => $student->religion],
            ['key' => 'personal_info.birthplace', 'label' => 'Birthplace', 'section' => 'personal', 'value' => $personalInfo?->birthplace ?? $personalInfo?->place_of_birth],
            ['key' => 'personal_info.citizenship', 'label' => 'Citizenship', 'section' => 'personal', 'value' => $personalInfo?->citizenship],
            ['key' => 'personal_info.current_address', 'label' => 'Current address', 'section' => 'personal', 'value' => $personalInfo?->current_adress],
            ['key' => 'personal_info.permanent_address', 'label' => 'Permanent address', 'section' => 'personal', 'value' => $personalInfo?->permanent_address],
            ['key' => 'contacts.emergency_contact_name', 'label' => 'Emergency contact name', 'section' => 'family', 'value' => $contacts?->emergency_contact_name, 'example' => 'Maria Dela Cruz'],
            ['key' => 'contacts.emergency_contact_phone', 'label' => 'Emergency contact phone', 'section' => 'family', 'value' => $contacts?->emergency_contact_phone, 'example' => '09123456789'],
            ['key' => 'contacts.emergency_contact_relationship', 'label' => 'Emergency contact relationship', 'section' => 'family', 'value' => $contacts?->emergency_contact_relationship, 'example' => 'Mother'],
            ['key' => 'contacts.personal_contact', 'label' => 'Personal contact', 'section' => 'family', 'value' => $contacts?->personal_contact, 'example' => '+63 912 345 6789'],
            ['key' => 'parents.guardian_name', 'label' => 'Guardian name', 'section' => 'family', 'value' => $parents?->guardian_name, 'example' => 'Maria Dela Cruz'],
            ['key' => 'parents.guardian_relationship', 'label' => 'Guardian relationship', 'section' => 'family', 'value' => $parents?->guardian_relationship, 'example' => 'Mother'],
            ['key' => 'parents.guardian_contact', 'label' => 'Guardian contact', 'section' => 'family', 'value' => $parents?->guardian_contact, 'example' => '+63 912 345 6789'],
            ['key' => 'education.elementary_school', 'label' => 'Elementary school', 'section' => 'education', 'value' => $education?->elementary_school],
            ['key' => 'education.elementary_year_graduated', 'label' => 'Elementary year graduated', 'section' => 'education', 'value' => $education?->elementary_year_graduated ?? $education?->elementary_graduate_year, 'example' => '2016'],
            ['key' => 'education.high_school', 'label' => 'High school', 'section' => 'education', 'value' => $education?->high_school ?? $education?->junior_high_school_name],
            ['key' => 'education.high_school_year_graduated', 'label' => 'High school year graduated', 'section' => 'education', 'value' => $education?->high_school_year_graduated ?? $education?->junior_high_graduation_year, 'example' => '2020'],
            ['key' => 'education.senior_high_school', 'label' => 'Senior high school', 'section' => 'education', 'value' => $education?->senior_high_school ?? $education?->senior_high_name],
            ['key' => 'education.senior_high_year_graduated', 'label' => 'Senior high year graduated', 'section' => 'education', 'value' => $education?->senior_high_year_graduated ?? $education?->senior_high_graduate_year, 'example' => '2022'],
            ['key' => 'ethnicity', 'label' => 'Ethnicity', 'section' => 'reporting', 'value' => $student->ethnicity],
            ['key' => 'city_of_origin', 'label' => 'City of origin', 'section' => 'reporting', 'value' => $student->city_of_origin],
            ['key' => 'province_of_origin', 'label' => 'Province of origin', 'section' => 'reporting', 'value' => $student->province_of_origin],
            ['key' => 'region_of_origin', 'label' => 'Region of origin', 'section' => 'reporting', 'value' => $student->region_of_origin],
            ['key' => 'income_bracket', 'label' => 'Household income bracket', 'section' => 'reporting', 'value' => $incomeValue],
            ['key' => 'profile_reporting_confirmed_at', 'label' => 'Reporting information review', 'section' => 'reporting', 'value' => $student->profile_reporting_confirmed_at],
        ];
    }

    private function filled(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return mb_trim($value) !== '';
        }

        return true;
    }
}
