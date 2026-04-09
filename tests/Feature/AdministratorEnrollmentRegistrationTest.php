<?php

declare(strict_types=1);

use App\Enums\StudentStatus;
use App\Models\Course;
use App\Models\Student;
use Inertia\Testing\AssertableInertia;

function portalUrlForPublicEnrollment(string $path): string
{
    $normalized = str_starts_with($path, '/') ? $path : "/{$path}";

    return 'http://'.env('PORTAL_HOST', 'portal.koakademy.test').$normalized;
}

it('allows guests to open the online enrollment form', function (): void {
    $this->get(portalUrlForPublicEnrollment('/enrollment'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('enrollment/index', false)
            ->has('departments')
            ->has('courses')
        );
});

it('creates a student applicant from the online enrollment form', function (): void {
    $course = Course::factory()->create([
        'department' => 'IT',
        'is_active' => true,
    ]);

    $payload = [
        'student_type' => 'college',
        'department' => 'IT',
        'course_id' => $course->id,
        'academic_year' => 1,
        'first_name' => 'Juan',
        'middle_name' => 'Dela',
        'last_name' => 'Cruz',
        'suffix' => '',
        'birth_date' => '2004-05-10',
        'gender' => 'male',
        'civil_status' => 'single',
        'nationality' => 'Filipino',
        'religion' => 'Roman Catholic',
        'email' => 'juan.cruz@example.com',
        'phone' => '09171234567',
        'address' => 'Purok 1, Brgy. Example, Davao City',
        'contacts' => [
            'personal_contact' => '09179998888',
            'emergency_contact_name' => 'Maria Cruz',
            'emergency_contact_phone' => '09170001111',
            'emergency_contact_relationship' => 'Mother',
        ],
        'parents' => [
            'father_name' => 'Jose Cruz',
            'father_contact' => '09170002222',
            'mother_name' => 'Maria Cruz',
            'mother_contact' => '09170001111',
            'guardian_name' => 'Maria Cruz',
            'guardian_relationship' => 'Mother',
            'guardian_contact' => '09170001111',
            'family_address' => 'Same as applicant address',
        ],
        'education' => [
            'elementary_school' => 'Example Elementary School',
            'elementary_year_graduated' => '2016',
            'high_school' => 'Example High School',
            'high_school_year_graduated' => '2020',
            'senior_high_school' => 'Example Senior High School',
            'senior_high_year_graduated' => '2022',
            'vocational_school' => '',
            'vocational_course' => '',
            'vocational_year_graduated' => '',
        ],
        'consent' => true,
    ];

    $this->post(portalUrlForPublicEnrollment('/enrollment'), $payload)
        ->assertRedirect('/enrollment');

    $student = Student::query()
        ->where('email', 'juan.cruz@example.com')
        ->first();

    expect($student)->not->toBeNull();
    expect($student->status)->toBe(StudentStatus::Applicant);
    expect($student->course_id)->toBe($course->id);
    expect($student->student_id)->not()->toBeNull();

    $student->refresh();

    expect($student->student_contact_id)->not()->toBeNull();
    expect($student->student_parent_info)->not()->toBeNull();
    expect($student->student_education_id)->not()->toBeNull();
});
