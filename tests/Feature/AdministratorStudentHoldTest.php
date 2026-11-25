<?php

declare(strict_types=1);

use App\Enums\UserRole;
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
        'semester' => 1,
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
                    [
                        'key' => 'cashier_verification',
                        'status' => 'Verified By Cashier',
                        'label' => 'Enrolled',
                        'color' => 'green',
                        'allowed_roles' => ['cashier'],
                        'action_type' => 'cashier_verification',
                    ],
                ],
                'entry_step_key' => 'pending',
                'completion_step_key' => 'cashier_verification',
            ],
        ],
    ]);
});

it('allows administrators to place, view, and release cross-office student holds', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create();

    $this->actingAs($admin)
        ->post(portalUrlForAdministrators('/administrators/enrollments/holds'), [
            'student_id' => $student->id,
            'office' => 'library',
            'severity' => 'blocking',
            'title' => 'Overdue Library Book',
            'reason' => 'Unreturned textbook from prior semester.',
            'blocks_enrollment' => true,
            'blocks_add_drop' => true,
            'blocks_grade_release' => false,
        ])
        ->assertRedirect();

    $hold = StudentHold::query()->firstOrFail();
    expect($hold->office)->toBe('library');
    expect($hold->status)->toBe(StudentHold::StatusActive);

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators('/administrators/enrollments/holds'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/enrollments/holds', false)
            ->where('stats.active', 1)
            ->where('stats.blocking_enrollment', 1)
            ->where('holds.data.0.title', 'Overdue Library Book'),
        );

    $this->actingAs($admin)
        ->post(portalUrlForAdministrators("/administrators/enrollments/holds/{$hold->id}/release"), [
            'release_notes' => 'Book returned and fine paid.',
        ])
        ->assertRedirect();

    $hold->refresh();
    expect($hold->status)->toBe(StudentHold::StatusReleased);
    expect($hold->released_by)->toBe($admin->id);
    expect($hold->release_notes)->toBe('Book returned and fine paid.');
});

it('blocks enrollment creation when a blocking hold exists and records audited override when bypassed', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::factory()->create(['lec_per_unit' => 500, 'lab_per_unit' => 600]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'lecture' => 3, 'laboratory' => 0]);
    $student = Student::factory()->create(['course_id' => $course->id]);

    $hold = StudentHold::query()->create([
        'student_id' => $student->id,
        'office' => 'accounting',
        'severity' => StudentHold::SeverityBlocking,
        'title' => 'Prior-Term Unpaid Balance',
        'reason' => 'Outstanding tuition balance from previous semester.',
        'blocks_enrollment' => true,
        'status' => StudentHold::StatusActive,
        'placed_by' => $admin->id,
        'placed_at' => now(),
    ]);

    $payload = [
        'student_id' => $student->id,
        'semester' => 1,
        'academic_year' => 1,
        'subjects' => [[
            'subject_id' => $subject->id,
            'class_id' => null,
            'is_modular' => false,
            'exclude_from_tuition' => false,
            'lecture_fee' => 1500,
            'laboratory_fee' => 0,
            'enrolled_lecture_units' => 3,
            'enrolled_laboratory_units' => 0,
        ]],
        'discount' => 0,
        'miscellaneous_fee' => 1000,
        'downpayment' => 0,
    ];

    // Without override: blocked
    $this->actingAs($admin)
        ->post(portalUrlForAdministrators('/administrators/enrollments'), $payload)
        ->assertRedirect();
    expect(StudentEnrollment::query()->count())->toBe(0);

    // With override + audited reason: allowed and hold recorded as overridden
    $this->actingAs($admin)
        ->post(portalUrlForAdministrators('/administrators/enrollments'), [
            ...$payload,
            'override_holds' => true,
            'override_holds_reason' => 'Approved promissory note #PN-2026-88',
        ])
        ->assertRedirect();

    expect(StudentEnrollment::query()->count())->toBe(1);
    $hold->refresh();
    expect($hold->status)->toBe(StudentHold::StatusOverridden);
    expect($hold->overridden_by)->toBe($admin->id);
    expect($hold->override_reason)->toBe('Approved promissory note #PN-2026-88');
});

it('surfaces active holds on enrollment rows and the dossier summary', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::factory()->create();
    $student = Student::factory()->create(['course_id' => $course->id]);

    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'status' => 'Pending',
    ]);

    StudentHold::query()->create([
        'student_id' => $student->id,
        'office' => 'registrar',
        'severity' => StudentHold::SeverityBlocking,
        'title' => 'Missing Form 137',
        'reason' => 'Original Form 137 not yet submitted.',
        'blocks_enrollment' => true,
        'status' => StudentHold::StatusActive,
        'placed_by' => $admin->id,
        'placed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators('/administrators/enrollments'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('enrollments.data.0.active_holds_count', 1)
            ->where('enrollments.data.0.blocking_holds_count', 1),
        );

    $this->actingAs($admin)
        ->getJson(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}/summary"))
        ->assertOk()
        ->assertJsonPath('holds.0.title', 'Missing Form 137')
        ->assertJsonPath('holds.0.office', 'registrar');
});
