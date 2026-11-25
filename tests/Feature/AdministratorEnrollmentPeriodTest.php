<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\AdditionalFee;
use App\Models\Course;
use App\Models\EnrollmentPeriod;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\User;
use App\Services\EnrollmentPeriodService;
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

it('allows administrators to configure the enrollment registration window and surfaces it on the index', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->put(portalUrlForAdministrators('/administrators/enrollments/period'), [
            'regular_starts_at' => now()->subDays(2)->toDateTimeString(),
            'regular_ends_at' => now()->addDays(5)->toDateTimeString(),
            'late_starts_at' => now()->addDays(6)->toDateTimeString(),
            'late_ends_at' => now()->addDays(10)->toDateTimeString(),
            'add_drop_deadline_at' => now()->addDays(14)->toDateTimeString(),
            'late_fee_label' => 'Late Registration Penalty',
            'late_fee_amount' => 750,
            'auto_apply_late_fee' => true,
            'enforce_windows' => true,
            'is_manually_closed' => false,
            'notes' => 'Regular window open for Semester 1.',
        ])
        ->assertRedirect();

    $period = EnrollmentPeriod::query()->first();
    expect($period)->not->toBeNull();
    expect((float) $period->late_fee_amount)->toBe(750.0);
    expect($period->enforce_windows)->toBeTrue();

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators('/administrators/enrollments'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('enrollment_period.configured', true)
            ->where('enrollment_period.phase', 'regular')
            ->where('enrollment_period.can_enroll', true)
            ->where('enrollment_period.add_drop_open', true)
            ->where('enrollment_period.late_fee_label', 'Late Registration Penalty'),
        );
});

it('blocks enrollment creation when window enforcement is active and closed, unless overridden', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::factory()->create(['lec_per_unit' => 500, 'lab_per_unit' => 600]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'lecture' => 3, 'laboratory' => 0]);
    $student = Student::factory()->create(['course_id' => $course->id]);

    EnrollmentPeriod::query()->create([
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'regular_starts_at' => now()->subDays(20),
        'regular_ends_at' => now()->subDays(10),
        'late_starts_at' => now()->subDays(9),
        'late_ends_at' => now()->subDays(2),
        'enforce_windows' => true,
        'is_manually_closed' => false,
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

    $this->actingAs($admin)
        ->post(portalUrlForAdministrators('/administrators/enrollments'), $payload)
        ->assertRedirect();

    expect(StudentEnrollment::query()->count())->toBe(0);

    $this->actingAs($admin)
        ->post(portalUrlForAdministrators('/administrators/enrollments'), [
            ...$payload,
            'override_window' => true,
        ])
        ->assertRedirect();

    expect(StudentEnrollment::query()->count())->toBe(1);
});

it('automatically appends the configured late fee during the late enrollment window', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::factory()->create(['lec_per_unit' => 500, 'lab_per_unit' => 600]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'lecture' => 3, 'laboratory' => 0]);
    $student = Student::factory()->create(['course_id' => $course->id]);

    EnrollmentPeriod::query()->create([
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'regular_starts_at' => now()->subDays(15),
        'regular_ends_at' => now()->subDays(3),
        'late_starts_at' => now()->subDays(2),
        'late_ends_at' => now()->addDays(4),
        'late_fee_label' => 'Late Enrollment Surcharge',
        'late_fee_amount' => 500,
        'auto_apply_late_fee' => true,
        'enforce_windows' => true,
    ]);

    $this->actingAs($admin)
        ->post(portalUrlForAdministrators('/administrators/enrollments'), [
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
        ])
        ->assertRedirect();

    $enrollment = StudentEnrollment::query()->firstOrFail();
    $lateFee = AdditionalFee::query()
        ->where('enrollment_id', $enrollment->id)
        ->where('fee_name', 'Late Enrollment Surcharge')
        ->first();

    expect($lateFee)->not->toBeNull();
    expect((float) $lateFee->amount)->toBe(500.0);
    expect(app(EnrollmentPeriodService::class)->describe()['phase'])->toBe('late');
});
