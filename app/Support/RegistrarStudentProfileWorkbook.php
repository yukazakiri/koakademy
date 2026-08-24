<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Student;
use BackedEnum;
use Carbon\CarbonInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use JsonException;

final class RegistrarStudentProfileWorkbook
{
    public const int SCHEMA_VERSION = 1;

    public const string DETAILS_SHEET = 'Enrollment Details';

    public const string METADATA_SHEET = 'Import Metadata';

    public const string BASELINE_SHEET = 'Import Baseline';

    /** @var list<array{key: string, label: string}> */
    public const array REFERENCE_COLUMNS = [
        ['key' => 'record_key', 'label' => 'Import Record Key'],
        ['key' => 'student_reference', 'label' => 'Student ID'],
        ['key' => 'student_name', 'label' => 'Student Name'],
        ['key' => 'department', 'label' => 'Department'],
        ['key' => 'course_code', 'label' => 'Course Code'],
        ['key' => 'course_title', 'label' => 'Course Title'],
        ['key' => 'year_level', 'label' => 'Year Level'],
        ['key' => 'intake_category', 'label' => 'First-year Intake Classification'],
        ['key' => 'status', 'label' => 'Enrollment Status'],
        ['key' => 'enrolled_at', 'label' => 'Enrolled At'],
    ];

    /**
     * @var list<array{
     *     key: string,
     *     label: string,
     *     group: string,
     *     type: string,
     *     max?: int,
     *     read: list<string>,
     *     write: list<string>,
     *     options?: array<string, string>
     * }>
     */
    private const array PROFILE_FIELDS = [
        ['key' => 'first_name', 'label' => 'First Name', 'group' => 'Identity', 'type' => 'string', 'max' => 100, 'read' => ['student.first_name'], 'write' => ['student.first_name']],
        ['key' => 'middle_name', 'label' => 'Middle Name', 'group' => 'Identity', 'type' => 'string', 'max' => 100, 'read' => ['student.middle_name'], 'write' => ['student.middle_name']],
        ['key' => 'last_name', 'label' => 'Last Name', 'group' => 'Identity', 'type' => 'string', 'max' => 100, 'read' => ['student.last_name'], 'write' => ['student.last_name']],
        ['key' => 'suffix', 'label' => 'Suffix', 'group' => 'Identity', 'type' => 'string', 'max' => 20, 'read' => ['student.suffix'], 'write' => ['student.suffix']],
        ['key' => 'gender', 'label' => 'Gender', 'group' => 'Identity', 'type' => 'choice', 'read' => ['student.gender'], 'write' => ['student.gender'], 'options' => ['male' => 'Male', 'female' => 'Female', 'other' => 'Other']],
        ['key' => 'birth_date', 'label' => 'Birth Date', 'group' => 'Identity', 'type' => 'date', 'read' => ['student.birth_date'], 'write' => ['student.birth_date']],
        ['key' => 'email', 'label' => 'Email', 'group' => 'Contact', 'type' => 'email', 'max' => 255, 'read' => ['student.email'], 'write' => ['student.email']],
        ['key' => 'phone', 'label' => 'Phone / Personal Contact', 'group' => 'Contact', 'type' => 'string', 'max' => 30, 'read' => ['contact.personal_contact', 'student.phone', 'contacts.personal_contact'], 'write' => ['student.phone', 'contact.personal_contact', 'contacts.personal_contact']],
        ['key' => 'civil_status', 'label' => 'Civil Status', 'group' => 'Personal', 'type' => 'string', 'max' => 50, 'read' => ['student.civil_status', 'personal.civil_status'], 'write' => ['student.civil_status', 'personal.civil_status']],
        ['key' => 'nationality', 'label' => 'Nationality / Citizenship', 'group' => 'Personal', 'type' => 'string', 'max' => 50, 'read' => ['student.nationality', 'personal.citizenship', 'contacts.personal_info.citizenship'], 'write' => ['student.nationality', 'personal.citizenship', 'contacts.personal_info.citizenship']],
        ['key' => 'religion', 'label' => 'Religion', 'group' => 'Personal', 'type' => 'string', 'max' => 50, 'read' => ['student.religion', 'personal.religion'], 'write' => ['student.religion', 'personal.religion']],
        ['key' => 'current_address', 'label' => 'Current Address', 'group' => 'Address', 'type' => 'string', 'max' => 500, 'read' => ['personal.current_adress', 'student.address', 'contacts.personal_info.current_address'], 'write' => ['student.address', 'personal.current_adress', 'contacts.personal_info.current_address']],
        ['key' => 'permanent_address', 'label' => 'Permanent Address', 'group' => 'Address', 'type' => 'string', 'max' => 500, 'read' => ['personal.permanent_address', 'contacts.personal_info.permanent_address'], 'write' => ['personal.permanent_address', 'contacts.personal_info.permanent_address']],
        ['key' => 'birthplace', 'label' => 'Birthplace', 'group' => 'Personal', 'type' => 'string', 'max' => 255, 'read' => ['personal.birthplace', 'personal.place_of_birth', 'contacts.personal_info.birthplace'], 'write' => ['personal.birthplace', 'personal.place_of_birth', 'contacts.personal_info.birthplace']],
        ['key' => 'weight', 'label' => 'Weight', 'group' => 'Personal', 'type' => 'number', 'read' => ['personal.weight', 'contacts.personal_info.weight'], 'write' => ['personal.weight', 'contacts.personal_info.weight']],
        ['key' => 'height', 'label' => 'Height', 'group' => 'Personal', 'type' => 'number', 'read' => ['personal.height', 'contacts.personal_info.height'], 'write' => ['personal.height', 'contacts.personal_info.height']],
        ['key' => 'ethnicity', 'label' => 'Ethnicity', 'group' => 'Origin and Equity', 'type' => 'string', 'max' => 100, 'read' => ['student.ethnicity'], 'write' => ['student.ethnicity']],
        ['key' => 'region_of_origin', 'label' => 'Region of Origin', 'group' => 'Origin and Equity', 'type' => 'string', 'max' => 100, 'read' => ['student.region_of_origin'], 'write' => ['student.region_of_origin']],
        ['key' => 'province_of_origin', 'label' => 'Province of Origin', 'group' => 'Origin and Equity', 'type' => 'string', 'max' => 100, 'read' => ['student.province_of_origin'], 'write' => ['student.province_of_origin']],
        ['key' => 'city_of_origin', 'label' => 'City / Municipality of Origin', 'group' => 'Origin and Equity', 'type' => 'string', 'max' => 100, 'read' => ['student.city_of_origin'], 'write' => ['student.city_of_origin']],
        ['key' => 'is_indigenous_person', 'label' => 'Indigenous Person', 'group' => 'Origin and Equity', 'type' => 'boolean', 'read' => ['student.is_indigenous_person'], 'write' => ['student.is_indigenous_person']],
        ['key' => 'indigenous_group', 'label' => 'Indigenous Group', 'group' => 'Origin and Equity', 'type' => 'string', 'max' => 100, 'read' => ['student.indigenous_group'], 'write' => ['student.indigenous_group']],
        ['key' => 'is_pwd', 'label' => 'Person with Disability', 'group' => 'Origin and Equity', 'type' => 'boolean', 'read' => ['student.is_pwd'], 'write' => ['student.is_pwd']],
        ['key' => 'pwd_type', 'label' => 'Disability Type', 'group' => 'Origin and Equity', 'type' => 'string', 'max' => 100, 'read' => ['student.pwd_type'], 'write' => ['student.pwd_type']],
        ['key' => 'is_solo_parent', 'label' => 'Solo Parent', 'group' => 'Origin and Equity', 'type' => 'boolean', 'read' => ['student.is_solo_parent'], 'write' => ['student.is_solo_parent']],
        ['key' => 'is_senior_citizen', 'label' => 'Senior Citizen', 'group' => 'Origin and Equity', 'type' => 'boolean', 'read' => ['student.is_senior_citizen'], 'write' => ['student.is_senior_citizen']],
        ['key' => 'is_magna_carta', 'label' => 'Magna Carta Beneficiary', 'group' => 'Origin and Equity', 'type' => 'boolean', 'read' => ['student.is_magna_carta'], 'write' => ['student.is_magna_carta']],
        ['key' => 'is_underprivileged', 'label' => 'Underprivileged', 'group' => 'Origin and Equity', 'type' => 'boolean', 'read' => ['student.is_underprivileged'], 'write' => ['student.is_underprivileged']],
        ['key' => 'is_first_generation', 'label' => 'First-generation Student', 'group' => 'Origin and Equity', 'type' => 'boolean', 'read' => ['student.is_first_generation'], 'write' => ['student.is_first_generation']],
        ['key' => 'family_income_bracket', 'label' => 'Family Income Bracket', 'group' => 'Origin and Equity', 'type' => 'string', 'max' => 50, 'read' => ['student.family_income_bracket'], 'write' => ['student.family_income_bracket']],
        ['key' => 'father_income_bracket', 'label' => 'Father Income Bracket', 'group' => 'Origin and Equity', 'type' => 'string', 'max' => 50, 'read' => ['student.father_income_bracket'], 'write' => ['student.father_income_bracket']],
        ['key' => 'mother_income_bracket', 'label' => 'Mother Income Bracket', 'group' => 'Origin and Equity', 'type' => 'string', 'max' => 50, 'read' => ['student.mother_income_bracket'], 'write' => ['student.mother_income_bracket']],
        ['key' => 'emergency_contact_name', 'label' => 'Emergency Contact Name', 'group' => 'Emergency Contact', 'type' => 'string', 'max' => 100, 'read' => ['contact.emergency_contact_name', 'student.emergency_contact', 'contacts.emergency_contact_name'], 'write' => ['student.emergency_contact', 'contact.emergency_contact_name', 'contacts.emergency_contact_name']],
        ['key' => 'emergency_contact_phone', 'label' => 'Emergency Contact Phone', 'group' => 'Emergency Contact', 'type' => 'string', 'max' => 30, 'read' => ['contact.emergency_contact_phone', 'contacts.emergency_contact_phone'], 'write' => ['contact.emergency_contact_phone', 'contacts.emergency_contact_phone']],
        ['key' => 'emergency_contact_address', 'label' => 'Emergency Contact Address', 'group' => 'Emergency Contact', 'type' => 'string', 'max' => 500, 'read' => ['contact.emergency_contact_address', 'contacts.emergency_contact_address'], 'write' => ['contact.emergency_contact_address', 'contacts.emergency_contact_address']],
        ['key' => 'emergency_contact_relationship', 'label' => 'Emergency Contact Relationship', 'group' => 'Emergency Contact', 'type' => 'string', 'max' => 100, 'read' => ['contact.emergency_contact_relationship', 'contacts.emergency_contact_relationship'], 'write' => ['contact.emergency_contact_relationship', 'contacts.emergency_contact_relationship']],
        ['key' => 'facebook_contact', 'label' => 'Facebook', 'group' => 'Contact', 'type' => 'string', 'max' => 255, 'read' => ['contact.facebook_contact', 'contact.facebook', 'contacts.facebook'], 'write' => ['contact.facebook_contact', 'contact.facebook', 'contacts.facebook']],
        ['key' => 'twitter', 'label' => 'X / Twitter', 'group' => 'Contact', 'type' => 'string', 'max' => 255, 'read' => ['contact.twitter', 'contacts.twitter'], 'write' => ['contact.twitter', 'contacts.twitter']],
        ['key' => 'instagram', 'label' => 'Instagram', 'group' => 'Contact', 'type' => 'string', 'max' => 255, 'read' => ['contact.instagram', 'contacts.instagram'], 'write' => ['contact.instagram', 'contacts.instagram']],
        ['key' => 'linkedin', 'label' => 'LinkedIn', 'group' => 'Contact', 'type' => 'string', 'max' => 255, 'read' => ['contact.linkedin', 'contacts.linkedin'], 'write' => ['contact.linkedin', 'contacts.linkedin']],
        ['key' => 'father_name', 'label' => "Father's Name", 'group' => 'Parent and Guardian', 'type' => 'string', 'max' => 100, 'read' => ['parent.father_name', 'parent.fathers_name', 'contacts.parents.father_name'], 'write' => ['parent.father_name', 'parent.fathers_name', 'contacts.parents.father_name']],
        ['key' => 'father_occupation', 'label' => "Father's Occupation", 'group' => 'Parent and Guardian', 'type' => 'string', 'max' => 100, 'read' => ['parent.father_occupation', 'contacts.parents.father_occupation'], 'write' => ['parent.father_occupation', 'contacts.parents.father_occupation']],
        ['key' => 'father_contact', 'label' => "Father's Contact", 'group' => 'Parent and Guardian', 'type' => 'string', 'max' => 30, 'read' => ['parent.father_contact', 'contacts.parents.father_contact'], 'write' => ['parent.father_contact', 'contacts.parents.father_contact']],
        ['key' => 'father_email', 'label' => "Father's Email", 'group' => 'Parent and Guardian', 'type' => 'email', 'max' => 255, 'read' => ['parent.father_email', 'contacts.parents.father_email'], 'write' => ['parent.father_email', 'contacts.parents.father_email']],
        ['key' => 'mother_name', 'label' => "Mother's Name", 'group' => 'Parent and Guardian', 'type' => 'string', 'max' => 100, 'read' => ['parent.mother_name', 'parent.mothers_name', 'contacts.parents.mother_name'], 'write' => ['parent.mother_name', 'parent.mothers_name', 'contacts.parents.mother_name']],
        ['key' => 'mother_occupation', 'label' => "Mother's Occupation", 'group' => 'Parent and Guardian', 'type' => 'string', 'max' => 100, 'read' => ['parent.mother_occupation', 'contacts.parents.mother_occupation'], 'write' => ['parent.mother_occupation', 'contacts.parents.mother_occupation']],
        ['key' => 'mother_contact', 'label' => "Mother's Contact", 'group' => 'Parent and Guardian', 'type' => 'string', 'max' => 30, 'read' => ['parent.mother_contact', 'contacts.parents.mother_contact'], 'write' => ['parent.mother_contact', 'contacts.parents.mother_contact']],
        ['key' => 'mother_email', 'label' => "Mother's Email", 'group' => 'Parent and Guardian', 'type' => 'email', 'max' => 255, 'read' => ['parent.mother_email', 'contacts.parents.mother_email'], 'write' => ['parent.mother_email', 'contacts.parents.mother_email']],
        ['key' => 'guardian_name', 'label' => 'Guardian Name', 'group' => 'Parent and Guardian', 'type' => 'string', 'max' => 100, 'read' => ['parent.guardian_name', 'contacts.parents.guardian_name'], 'write' => ['parent.guardian_name', 'contacts.parents.guardian_name']],
        ['key' => 'guardian_relationship', 'label' => 'Guardian Relationship', 'group' => 'Parent and Guardian', 'type' => 'string', 'max' => 100, 'read' => ['parent.guardian_relationship', 'contacts.parents.guardian_relationship'], 'write' => ['parent.guardian_relationship', 'contacts.parents.guardian_relationship']],
        ['key' => 'guardian_contact', 'label' => 'Guardian Contact', 'group' => 'Parent and Guardian', 'type' => 'string', 'max' => 30, 'read' => ['parent.guardian_contact', 'contacts.parents.guardian_contact'], 'write' => ['parent.guardian_contact', 'contacts.parents.guardian_contact']],
        ['key' => 'guardian_email', 'label' => 'Guardian Email', 'group' => 'Parent and Guardian', 'type' => 'email', 'max' => 255, 'read' => ['parent.guardian_email', 'contacts.parents.guardian_email'], 'write' => ['parent.guardian_email', 'contacts.parents.guardian_email']],
        ['key' => 'family_address', 'label' => 'Family Address', 'group' => 'Parent and Guardian', 'type' => 'string', 'max' => 500, 'read' => ['parent.family_address', 'contacts.parents.family_address'], 'write' => ['parent.family_address', 'contacts.parents.family_address']],
        ['key' => 'elementary_school', 'label' => 'Elementary School', 'group' => 'Education', 'type' => 'string', 'max' => 255, 'read' => ['education.elementary_school', 'contacts.education.elementary_school'], 'write' => ['education.elementary_school', 'contacts.education.elementary_school']],
        ['key' => 'elementary_graduate_year', 'label' => 'Elementary Graduation Year', 'group' => 'Education', 'type' => 'year', 'read' => ['education.elementary_graduate_year', 'education.elementary_year_graduated', 'contacts.education.elementary_year_graduated'], 'write' => ['education.elementary_graduate_year', 'education.elementary_year_graduated', 'contacts.education.elementary_year_graduated']],
        ['key' => 'elementary_school_address', 'label' => 'Elementary School Address', 'group' => 'Education', 'type' => 'string', 'max' => 500, 'read' => ['education.elementary_school_address', 'contacts.education.elementary_school_address'], 'write' => ['education.elementary_school_address', 'contacts.education.elementary_school_address']],
        ['key' => 'junior_high_school_name', 'label' => 'Junior High School', 'group' => 'Education', 'type' => 'string', 'max' => 255, 'read' => ['education.junior_high_school_name', 'education.high_school', 'contacts.education.high_school'], 'write' => ['education.junior_high_school_name', 'education.high_school', 'contacts.education.high_school']],
        ['key' => 'junior_high_graduation_year', 'label' => 'Junior High Graduation Year', 'group' => 'Education', 'type' => 'year', 'read' => ['education.junior_high_graduation_year', 'education.high_school_year_graduated', 'contacts.education.high_school_year_graduated'], 'write' => ['education.junior_high_graduation_year', 'education.high_school_year_graduated', 'contacts.education.high_school_year_graduated']],
        ['key' => 'junior_high_school_address', 'label' => 'Junior High School Address', 'group' => 'Education', 'type' => 'string', 'max' => 500, 'read' => ['education.junior_high_school_address', 'contacts.education.junior_high_school_address'], 'write' => ['education.junior_high_school_address', 'contacts.education.junior_high_school_address']],
        ['key' => 'senior_high_name', 'label' => 'Senior High School', 'group' => 'Education', 'type' => 'string', 'max' => 255, 'read' => ['education.senior_high_name', 'education.senior_high_school', 'contacts.education.senior_high_school'], 'write' => ['education.senior_high_name', 'education.senior_high_school', 'contacts.education.senior_high_school']],
        ['key' => 'senior_high_graduate_year', 'label' => 'Senior High Graduation Year', 'group' => 'Education', 'type' => 'year', 'read' => ['education.senior_high_graduate_year', 'education.senior_high_year_graduated', 'contacts.education.senior_high_year_graduated'], 'write' => ['education.senior_high_graduate_year', 'education.senior_high_year_graduated', 'contacts.education.senior_high_year_graduated']],
        ['key' => 'senior_high_address', 'label' => 'Senior High School Address', 'group' => 'Education', 'type' => 'string', 'max' => 500, 'read' => ['education.senior_high_address', 'contacts.education.senior_high_address'], 'write' => ['education.senior_high_address', 'contacts.education.senior_high_address']],
        ['key' => 'college_school', 'label' => 'Previous College', 'group' => 'Education', 'type' => 'string', 'max' => 255, 'read' => ['education.college_school', 'contacts.education.college_school'], 'write' => ['education.college_school', 'contacts.education.college_school']],
        ['key' => 'college_course', 'label' => 'Previous College Course', 'group' => 'Education', 'type' => 'string', 'max' => 255, 'read' => ['education.college_course', 'contacts.education.college_course'], 'write' => ['education.college_course', 'contacts.education.college_course']],
        ['key' => 'college_year_graduated', 'label' => 'College Graduation Year', 'group' => 'Education', 'type' => 'year', 'read' => ['education.college_year_graduated', 'contacts.education.college_year_graduated'], 'write' => ['education.college_year_graduated', 'contacts.education.college_year_graduated']],
        ['key' => 'vocational_school', 'label' => 'Vocational School', 'group' => 'Education', 'type' => 'string', 'max' => 255, 'read' => ['education.vocational_school', 'contacts.education.vocational_school'], 'write' => ['education.vocational_school', 'contacts.education.vocational_school']],
        ['key' => 'vocational_course', 'label' => 'Vocational Course', 'group' => 'Education', 'type' => 'string', 'max' => 255, 'read' => ['education.vocational_course', 'contacts.education.vocational_course'], 'write' => ['education.vocational_course', 'contacts.education.vocational_course']],
        ['key' => 'vocational_year_graduated', 'label' => 'Vocational Graduation Year', 'group' => 'Education', 'type' => 'year', 'read' => ['education.vocational_year_graduated', 'contacts.education.vocational_year_graduated'], 'write' => ['education.vocational_year_graduated', 'contacts.education.vocational_year_graduated']],
        ['key' => 'scholarship_type', 'label' => 'Scholarship Type', 'group' => 'Scholarship and Employment', 'type' => 'choice', 'read' => ['student.scholarship_type'], 'write' => ['student.scholarship_type'], 'options' => ['none' => 'No Scholarship', 'tdp' => 'TDP', 'tes' => 'TES', 'institutional' => 'Institutional', 'private' => 'Private / External', 'other' => 'Other']],
        ['key' => 'scholarship_details', 'label' => 'Scholarship Details', 'group' => 'Scholarship and Employment', 'type' => 'string', 'max' => 1000, 'read' => ['student.scholarship_details'], 'write' => ['student.scholarship_details']],
        ['key' => 'employment_status', 'label' => 'Employment Status', 'group' => 'Scholarship and Employment', 'type' => 'choice', 'read' => ['student.employment_status'], 'write' => ['student.employment_status'], 'options' => ['not_applicable' => 'Not Applicable', 'unemployed' => 'Unemployed', 'employed' => 'Employed', 'self_employed' => 'Self-Employed', 'underemployed' => 'Underemployed', 'further_study' => 'Pursuing Further Study']],
        ['key' => 'employer_name', 'label' => 'Employer Name', 'group' => 'Scholarship and Employment', 'type' => 'string', 'max' => 255, 'read' => ['student.employer_name'], 'write' => ['student.employer_name']],
        ['key' => 'job_position', 'label' => 'Job Position', 'group' => 'Scholarship and Employment', 'type' => 'string', 'max' => 255, 'read' => ['student.job_position'], 'write' => ['student.job_position']],
        ['key' => 'employment_date', 'label' => 'Employment Date', 'group' => 'Scholarship and Employment', 'type' => 'date', 'read' => ['student.employment_date'], 'write' => ['student.employment_date']],
        ['key' => 'employed_by_institution', 'label' => 'Employed by Institution', 'group' => 'Scholarship and Employment', 'type' => 'boolean', 'read' => ['student.employed_by_institution'], 'write' => ['student.employed_by_institution']],
    ];

    /** @return list<array<string, mixed>> */
    public function fields(): array
    {
        return self::PROFILE_FIELDS;
    }

    /** @return array<string, mixed>|null */
    public function field(string $key): ?array
    {
        foreach (self::PROFILE_FIELDS as $field) {
            if ($field['key'] === $key) {
                return $field;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return [
            ...array_column(self::REFERENCE_COLUMNS, 'label'),
            ...array_column(self::PROFILE_FIELDS, 'label'),
        ];
    }

    /** @return array<string, string> */
    public function fieldKeysByHeading(): array
    {
        return collect(self::PROFILE_FIELDS)->mapWithKeys(
            fn (array $field): array => [$field['label'] => $field['key']]
        )->all();
    }

    /** @return array<string, mixed> */
    public function profileValues(Student $student): array
    {
        $values = [];

        foreach (self::PROFILE_FIELDS as $field) {
            $values[$field['key']] = $this->readValue($student, $field);
        }

        return $values;
    }

    /** @return list<string> */
    public function writeTargets(string $key): array
    {
        return $this->field($key)['write'] ?? [];
    }

    public function displayValue(string $key, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $field = $this->field($key);
        if ($field === null) {
            return (string) $value;
        }

        if ($field['type'] === 'boolean') {
            return filter_var($value, FILTER_VALIDATE_BOOL) ? 'Yes' : 'No';
        }

        if (isset($field['options'][(string) $value])) {
            return $field['options'][(string) $value];
        }

        return (string) $value;
    }

    public function displayIntakeCategory(?string $value): string
    {
        return match ($value) {
            'new_freshman' => 'New freshman',
            'continuing_first_year' => 'Continuing first-year',
            default => 'Unclassified',
        };
    }

    /** @return array{0: mixed, 1: string|null} */
    public function normalizeInput(string $key, mixed $value): array
    {
        $field = $this->field($key);
        if ($field === null) {
            return [null, 'This column is not importable.'];
        }

        $text = mb_trim((string) $value);
        if ($text === '') {
            return [null, null];
        }

        if ($field['type'] === 'boolean') {
            $normalized = match (mb_strtolower($text)) {
                'yes', 'true', '1' => true,
                'no', 'false', '0' => false,
                default => null,
            };

            return $normalized === null
                ? [null, $field['label'].' must be Yes or No.']
                : [$normalized, null];
        }

        if ($field['type'] === 'date') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text);
            $valid = $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $text;

            return $valid
                ? [$text, null]
                : [null, $field['label'].' must use YYYY-MM-DD.'];
        }

        if ($field['type'] === 'number') {
            return is_numeric($text) && (float) $text >= 0
                ? [(float) $text, null]
                : [null, $field['label'].' must be a non-negative number.'];
        }

        if ($field['type'] === 'year') {
            return preg_match('/^(19|20)\\d{2}$/', $text) === 1
                ? [$text, null]
                : [null, $field['label'].' must be a four-digit year.'];
        }

        if ($field['type'] === 'email' && filter_var($text, FILTER_VALIDATE_EMAIL) === false) {
            return [null, $field['label'].' must be a valid email address.'];
        }

        if ($field['type'] === 'choice') {
            $normalized = mb_strtolower($text);
            foreach ($field['options'] ?? [] as $optionValue => $label) {
                if ($normalized === mb_strtolower($optionValue) || $normalized === mb_strtolower($label)) {
                    return [$optionValue, null];
                }
            }

            return [null, $field['label'].' contains an unsupported value.'];
        }

        if (isset($field['max']) && mb_strlen($text) > $field['max']) {
            return [null, $field['label'].' may not exceed '.$field['max'].' characters.'];
        }

        return [$text, null];
    }

    /** @return array{0: string|null, 1: string|null} */
    public function normalizeIntakeCategory(mixed $value): array
    {
        $text = mb_strtolower(mb_trim((string) $value));

        return match ($text) {
            '', 'unclassified' => [null, null],
            'new_freshman', 'new freshman' => ['new_freshman', null],
            'continuing_first_year', 'continuing first-year', 'continuing first year' => ['continuing_first_year', null],
            default => [null, 'First-year Intake Classification must be New freshman or Continuing first-year.'],
        };
    }

    /** @param array<string, scalar|null> $metadata */
    public function metadataSignature(array $metadata): string
    {
        return $this->signature($metadata);
    }

    /** @param array<string, mixed> $baseline */
    public function baselineSignature(array $baseline): string
    {
        return $this->signature($baseline);
    }

    /** @param array<string, scalar|null> $metadata */
    public function verifyMetadata(array $metadata, string $signature): bool
    {
        return hash_equals($this->metadataSignature($metadata), $signature);
    }

    /** @param array<string, mixed> $baseline */
    public function verifyBaseline(array $baseline, string $signature): bool
    {
        return hash_equals($this->baselineSignature($baseline), $signature);
    }

    /** @param array<string, mixed> $row */
    public function recordKey(array $row, int $schoolId): string
    {
        return $this->signature([
            'school_id' => $schoolId,
            'student_id' => (int) ($row['student_record_id'] ?? 0),
            'enrollment_id' => (int) ($row['enrollment_record_id'] ?? 0),
            'student_updated_at' => (string) ($row['student_updated_at'] ?? ''),
            'enrollment_updated_at' => (string) ($row['enrollment_updated_at'] ?? ''),
        ]);
    }

    public function valuesEqual(mixed $first, mixed $second): bool
    {
        $firstIsBlank = $first === null || $first === '';
        $secondIsBlank = $second === null || $second === '';
        if ($firstIsBlank || $secondIsBlank) {
            return $firstIsBlank && $secondIsBlank;
        }

        if (is_bool($first) || is_bool($second)) {
            return (bool) $first === (bool) $second;
        }

        if (is_numeric($first) && is_numeric($second)) {
            return abs((float) $first - (float) $second) < 0.00001;
        }

        return (string) ($first ?? '') === (string) ($second ?? '');
    }

    /**
     * @param  iterable<int, array<string, mixed>|object>  $rows
     * @return list<array<string, mixed>>
     */
    public function prepareRows(iterable $rows, int $schoolId): array
    {
        $prepared = [];

        foreach ($rows as $source) {
            $row = is_array($source) ? $source : (method_exists($source, 'toArray') ? $source->toArray() : (array) $source);
            $row['record_key'] = $this->recordKey($row, $schoolId);

            $baseline = $this->baselinePayload($row, $schoolId);
            $row['baseline_signature'] = $this->baselineSignature($baseline);
            $prepared[] = $row;
        }

        return $prepared;
    }

    /** @param array<string, mixed> $row
     *  @return array<string, mixed> */
    public function baselinePayload(array $row, int $schoolId): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'record_key' => (string) ($row['record_key'] ?? ''),
            'school_id' => $schoolId,
            'student_record_id' => (int) ($row['student_record_id'] ?? 0),
            'enrollment_record_id' => (int) ($row['enrollment_record_id'] ?? 0),
            'student_updated_at' => (string) ($row['student_updated_at'] ?? ''),
            'enrollment_updated_at' => (string) ($row['enrollment_updated_at'] ?? ''),
            'profile_values' => $row['profile_values'] ?? [],
            'intake_category' => $row['intake_category'] ?? null,
            'year_level' => (int) ($row['year_level'] ?? 0),
        ];
    }

    /** @param array<string, mixed> $field */
    private function readValue(Student $student, array $field): mixed
    {
        foreach ($field['read'] as $path) {
            [$scope, $attribute] = explode('.', $path, 2);

            $value = match ($scope) {
                'student' => data_get($student, $attribute),
                'contact' => data_get($student->studentContactsInfo, $attribute),
                'parent' => data_get($student->studentParentInfo, $attribute),
                'education' => data_get($student->studentEducationInfo, $attribute),
                'personal' => data_get($student->personalInfo, $attribute),
                'contacts' => Arr::get(is_array($student->contacts) ? $student->contacts : [], $attribute),
                default => null,
            };

            if ($value !== null && $value !== '') {
                return $this->canonicalValue($value);
            }
        }

        return null;
    }

    private function canonicalValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof CarbonInterface || $value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value)) {
            return Str::of($value)->trim()->toString();
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private function signature(array $payload): string
    {
        $key = (string) config('app.key');

        try {
            $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            $json = serialize($payload);
        }

        return hash_hmac('sha256', $json, $key);
    }
}
