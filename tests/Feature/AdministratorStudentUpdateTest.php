<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\School;
use App\Models\Student;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    config(['activitylog.enabled' => false]);

    School::factory()->create();
});

it('creates missing related student records during administrator updates', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $course = Course::factory()->create();

    $student = Student::factory()->create([
        'course_id' => $course->id,
        'student_contact_id' => null,
        'student_parent_info' => null,
        'student_education_id' => null,
        'student_personal_id' => null,
    ]);

    actingAs($admin)
        ->put(route('administrators.students.update', $student), [
            'student_type' => 'college',
            'student_id' => '205590',
            'lrn' => null,
            'first_name' => 'Loreano',
            'last_name' => 'Lukkanit',
            'middle_name' => 'Dee',
            'gender' => 'male',
            'birth_date' => '2005-01-01',
            'email' => 'loreano@example.com',
            'course_id' => $course->id,
            'academic_year' => 1,
            'shs_strand_id' => null,
            'remarks' => 'Updated by admin',
            'personal_contact' => '09123456789',
            'emergency_contact_name' => 'Emergency Contact',
            'emergency_contact_phone' => '09987654321',
            'emergency_contact_address' => 'Davao City',
            'fathers_name' => 'Father Name',
            'mothers_name' => 'Mother Name',
            'elementary_school' => 'Elementary School',
            'elementary_graduate_year' => '2016',
            'elementary_school_address' => 'Elementary Address',
            'junior_high_school_name' => 'Junior High School',
            'junior_high_graduation_year' => '2020',
            'junior_high_school_address' => 'Junior High Address',
            'senior_high_name' => 'Senior High School',
            'senior_high_graduate_year' => '2022',
            'senior_high_address' => 'Senior High Address',
            'current_address' => 'Current Address',
            'permanent_address' => 'Permanent Address',
            'birthplace' => 'Davao City',
            'civil_status' => 'single',
            'citizenship' => 'Filipino',
            'religion' => 'Catholic',
            'weight' => 65,
            'height' => '170',
            'ethnicity' => 'Bagobo',
            'city_of_origin' => 'Davao City',
            'province_of_origin' => 'Davao del Sur',
            'region_of_origin' => 'Region XI',
            'is_indigenous_person' => false,
            'indigenous_group' => null,
            'status' => 'enrolled',
            'withdrawal_date' => null,
            'withdrawal_reason' => null,
            'attrition_category' => null,
            'dropout_date' => null,
            'scholarship_type' => 'none',
            'scholarship_details' => null,
            'employment_status' => 'not_applicable',
            'employer_name' => null,
            'job_position' => null,
            'employment_date' => null,
            'employed_by_institution' => false,
        ])
        ->assertRedirect(route('administrators.students.index'));

    $student->refresh();

    expect($student->student_contact_id)->not->toBeNull()
        ->and($student->student_parent_info)->not->toBeNull()
        ->and($student->student_education_id)->not->toBeNull()
        ->and($student->student_personal_id)->not->toBeNull();

    expect($student->studentContactsInfo)->not->toBeNull()
        ->and($student->studentContactsInfo?->personal_contact)->toBe('09123456789')
        ->and($student->studentContactsInfo?->emergency_contact_name)->toBe('Emergency Contact')
        ->and($student->studentContactsInfo?->emergency_contact_phone)->toBe('09987654321');

    expect($student->studentParentInfo)->not->toBeNull()
        ->and($student->studentParentInfo?->father_name)->toBe('Father Name')
        ->and($student->studentParentInfo?->mother_name)->toBe('Mother Name');

    expect($student->studentEducationInfo)->not->toBeNull()
        ->and($student->studentEducationInfo?->elementary_school)->toBe('Elementary School')
        ->and($student->studentEducationInfo?->elementary_year_graduated)->toBe('2016')
        ->and($student->studentEducationInfo?->high_school)->toBe('Junior High School')
        ->and($student->studentEducationInfo?->high_school_year_graduated)->toBe('2020')
        ->and($student->studentEducationInfo?->senior_high_school)->toBe('Senior High School')
        ->and($student->studentEducationInfo?->senior_high_year_graduated)->toBe('2022');

    expect($student->personalInfo)->not->toBeNull()
        ->and($student->personalInfo?->citizenship)->toBe('Filipino')
        ->and((float) $student->personalInfo?->weight)->toBe(65.0)
        ->and((float) $student->personalInfo?->height)->toBe(170.0);
});
