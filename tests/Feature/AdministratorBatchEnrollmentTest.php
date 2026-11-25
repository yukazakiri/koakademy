<?php

declare(strict_types=1);

use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Course;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentHold;
use App\Models\Subject;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    GeneralSetting::factory()->create([
        'school_starting_date' => '2025-08-01',
        'school_ending_date' => '2026-05-30',
        'semester' => 2,
        'more_configs' => [
            'enrollment_pipeline' => [
                'steps' => [
                    [
                        'key' => 'pending',
                        'status' => 'Pending',
                        'label' => 'Pending',
                        'color' => 'amber',
                        'allowed_roles' => ['student'],
                        'action_type' => 'standard',
                    ],
                ],
                'entry_step_key' => 'pending',
                'completion_step_key' => 'pending',
            ],
        ],
    ]);
});

it('renders the batch cohort enrollment wizard and previews eligible students and block sections', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::factory()->create(['code' => 'BSCS', 'lec_per_unit' => 500, 'lab_per_unit' => 600]);
    $subject = Subject::factory()->create([
        'course_id' => $course->id,
        'code' => 'CS202',
        'academic_year' => 2,
        'semester' => 2,
        'lecture' => 3,
        'laboratory' => 0,
    ]);
    Classes::factory()->create([
        'subject_code' => 'CS202',
        'subject_ids' => [$subject->id],
        'course_codes' => [$course->id],
        'section' => 'BSCS-2A',
        'school_year' => '2025 - 2026',
        'semester' => 2,
        'maximum_slots' => 40,
    ]);

    $eligible = Student::factory()->create([
        'course_id' => $course->id,
        'academic_year' => 1,
        'status' => StudentStatus::Enrolled,
    ]);

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators('/administrators/enrollments/batch'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/enrollments/batch', false)
            ->has('courses'),
        );

    $this->actingAs($admin)
        ->getJson(portalUrlForAdministrators('/administrators/enrollments/batch/preview?'.http_build_query([
            'course_id' => $course->id,
            'source_year_level' => 1,
            'target_year_level' => 2,
            'target_semester' => 2,
        ])))
        ->assertOk()
        ->assertJsonPath('students.0.id', $eligible->id)
        ->assertJsonPath('subjects.0.code', 'CS202')
        ->assertJsonPath('available_sections.0.section', 'BSCS-2A');
});

it('batch enrolls a cohort into a block section, promotes year levels, and blocks students with active holds', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::factory()->create(['code' => 'BSIT', 'lec_per_unit' => 500, 'lab_per_unit' => 600]);
    $subject = Subject::factory()->create([
        'course_id' => $course->id,
        'code' => 'IT202',
        'academic_year' => 2,
        'semester' => 2,
        'lecture' => 3,
        'laboratory' => 0,
    ]);
    $class = Classes::factory()->create([
        'subject_code' => 'IT202',
        'subject_ids' => [$subject->id],
        'course_codes' => [$course->id],
        'section' => 'BSIT-2A',
        'school_year' => '2025 - 2026',
        'semester' => 2,
        'maximum_slots' => 30,
    ]);

    $clearStudent = Student::factory()->create([
        'course_id' => $course->id,
        'academic_year' => 1,
        'status' => StudentStatus::Enrolled,
    ]);
    $heldStudent = Student::factory()->create([
        'course_id' => $course->id,
        'academic_year' => 1,
        'status' => StudentStatus::Enrolled,
    ]);
    StudentHold::query()->create([
        'student_id' => $heldStudent->id,
        'office' => 'accounting',
        'severity' => StudentHold::SeverityBlocking,
        'title' => 'Unpaid Tuition Balance',
        'reason' => 'Balance from previous semester.',
        'blocks_enrollment' => true,
        'status' => StudentHold::StatusActive,
        'placed_by' => $admin->id,
        'placed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->postJson(portalUrlForAdministrators('/administrators/enrollments/batch'), [
            'course_id' => $course->id,
            'target_year_level' => 2,
            'target_semester' => 2,
            'student_ids' => [$clearStudent->id, $heldStudent->id],
            'subject_ids' => [$subject->id],
            'block_section' => 'BSIT-2A',
            'auto_assign_classes' => true,
            'promote_student_year_level' => true,
        ])
        ->assertOk()
        ->assertJsonPath('enrolled_count', 1)
        ->assertJsonPath('failed_count', 1);

    expect($clearStudent->fresh()->academic_year)->toBe(2);
    expect($heldStudent->fresh()->academic_year)->toBe(1);
    expect(StudentEnrollment::query()->where('student_id', $clearStudent->id)->count())->toBe(1);
    expect(ClassEnrollment::query()->where('student_id', $clearStudent->id)->where('class_id', $class->id)->exists())->toBeTrue();
});
