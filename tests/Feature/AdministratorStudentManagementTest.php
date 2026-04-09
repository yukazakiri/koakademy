<?php

declare(strict_types=1);

use App\Enums\SubjectEnrolledEnum;
use App\Enums\UserRole;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    School::factory()->create();
});

it('updates an existing historical subject enrollment', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create();
    $subject = Subject::factory()->create();

    $studentEnrollmentPast = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'school_year' => '2022 - 2023',
        'semester' => 1,
    ]);

    // Create a past enrollment
    $pastEnrollment = SubjectEnrollment::create([
        'student_id' => $student->id,
        'enrollment_id' => $studentEnrollmentPast->id,
        'subject_id' => $subject->id,
        'grade' => 74.0,
        'academic_year' => 1,
        'school_year' => '2022 - 2023',
        'semester' => 1,
        'classification' => SubjectEnrolledEnum::INTERNAL->value,
    ]);

    $studentEnrollmentCurrent = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'school_year' => '2023 - 2024',
        'semester' => 1,
    ]);

    // Create a current enrollment
    $currentEnrollment = SubjectEnrollment::create([
        'student_id' => $student->id,
        'enrollment_id' => $studentEnrollmentCurrent->id,
        'subject_id' => $subject->id,
        'grade' => null,
        'academic_year' => 2,
        'school_year' => '2023 - 2024',
        'semester' => 1,
        'classification' => SubjectEnrolledEnum::INTERNAL->value,
    ]);

    // Ensure we are testing exactly 2 exist
    expect(SubjectEnrollment::where('student_id', $student->id)->count())->toBe(2);

    actingAs($user)
        ->patch(route('administrators.students.subjects.update-grade', ['student' => $student->id, 'subject' => $subject->id]), [
            'enrollment_record_id' => $pastEnrollment->id,
            'is_new_record' => false,
            'grade' => 75.0, // Changing past grade
            'remarks' => 'Passed on appeal',
            'classification' => SubjectEnrolledEnum::INTERNAL->value,
            'academic_year' => 1,
            'school_year' => '2022 - 2023',
            'semester' => 1,
        ])
        ->assertRedirect();

    // Verify
    expect($pastEnrollment->refresh()->grade)->toBe(75.0)
        ->and($pastEnrollment->refresh()->remarks)->toBe('Passed on appeal')
        ->and($currentEnrollment->refresh()->grade)->toBeNull();
});

it('creates a new historical subject enrollment if is_new_record is true', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create();
    $subject = Subject::factory()->create();

    // Initially 0 enrollments
    expect(SubjectEnrollment::where('student_id', $student->id)->count())->toBe(0);

    actingAs($user)
        ->patch(route('administrators.students.subjects.update-grade', ['student' => $student->id, 'subject' => $subject->id]), [
            'is_new_record' => true,
            'grade' => 74.0,
            'remarks' => 'Failed take 1',
            'classification' => SubjectEnrolledEnum::INTERNAL->value,
            'academic_year' => 1,
            'school_year' => '2022 - 2023',
            'semester' => 1,
        ])
        ->assertRedirect();

    expect(SubjectEnrollment::where('student_id', $student->id)->where('subject_id', $subject->id)->count())->toBe(1);

    $enrollment = SubjectEnrollment::where('student_id', $student->id)->first();
    expect($enrollment->grade)->toBe(74.0)
        ->and($enrollment->remarks)->toBe('Failed take 1');
});

it('creates non credited historical records without attaching them to the clicked curriculum subject', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create();
    $subject = Subject::factory()->create([
        'academic_year' => 1,
        'semester' => 1,
        'course_id' => $student->course_id,
    ]);

    actingAs($user)
        ->patch(route('administrators.students.subjects.update-grade', ['student' => $student->id, 'subject' => $subject->id]), [
            'is_new_record' => true,
            'grade' => 88,
            'remarks' => 'Taken externally with no equivalent',
            'classification' => SubjectEnrolledEnum::NON_CREDITED->value,
            'school_name' => 'External Academy',
            'external_subject_code' => 'EXT-101',
            'external_subject_title' => 'External Logic',
            'external_subject_units' => 3,
            'academic_year' => 1,
            'school_year' => '2022 - 2023',
            'semester' => 1,
        ])
        ->assertRedirect();

    $enrollment = SubjectEnrollment::query()->where('student_id', $student->id)->sole();

    expect($enrollment->classification)->toBe(SubjectEnrolledEnum::NON_CREDITED->value)
        ->and($enrollment->subject_id)->toBeNull()
        ->and($enrollment->credited_subject_id)->toBeNull()
        ->and($enrollment->external_subject_code)->toBe('EXT-101')
        ->and($enrollment->external_subject_title)->toBe('External Logic');
});

it('shows standalone non credited records separately from the checklist payload', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create();
    $studentEnrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'school_year' => '2023 - 2024',
        'semester' => 1,
    ]);
    $historicalEnrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'school_year' => '2022 - 2023',
        'semester' => 1,
    ]);
    $curriculumSubject = Subject::factory()->create([
        'academic_year' => 1,
        'semester' => 1,
        'course_id' => $student->course_id,
        'code' => 'MATH101',
        'title' => 'College Algebra',
    ]);

    SubjectEnrollment::create([
        'student_id' => $student->id,
        'enrollment_id' => $studentEnrollment->id,
        'subject_id' => $curriculumSubject->id,
        'grade' => 85,
        'academic_year' => 1,
        'school_year' => '2023 - 2024',
        'semester' => 1,
        'classification' => SubjectEnrolledEnum::INTERNAL->value,
    ]);

    SubjectEnrollment::create([
        'student_id' => $student->id,
        'enrollment_id' => $historicalEnrollment->id,
        'subject_id' => null,
        'grade' => 90,
        'remarks' => 'No equivalent subject',
        'academic_year' => 1,
        'school_year' => '2022 - 2023',
        'semester' => 1,
        'classification' => SubjectEnrolledEnum::NON_CREDITED->value,
        'school_name' => 'External Academy',
        'external_subject_code' => 'EXT-101',
        'external_subject_title' => 'External Logic',
        'external_subject_units' => 3,
    ]);

    actingAs($user)
        ->get(route('administrators.students.show', $student->id))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/students/show', false)
            ->has('student.non_credited_subjects', 1)
            ->where('student.non_credited_subjects.0.external_subject_code', 'EXT-101')
            ->where('student.non_credited_subjects.0.linked_subject', null)
            ->where('student.checklist.0.semesters.0.subjects.0.code', 'MATH101'));
});
