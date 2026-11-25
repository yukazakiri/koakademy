<?php

declare(strict_types=1);

use App\Enums\ApplicantCancellationReason;
use App\Enums\ScholarshipType;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\User;
use App\Notifications\ApplicantApprovedForRequirements;
use Illuminate\Support\Facades\Notification;

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
                ],
                'entry_step_key' => 'pending',
                'completion_step_key' => 'pending',
            ],
        ],
    ]);
});

it('queues bulk approval notifications and skips applicants without emails', function (): void {
    Notification::fake();

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $withEmail = Student::factory()->create(['status' => StudentStatus::Applicant, 'email' => 'one@example.com']);
    $withoutEmail = Student::factory()->create(['status' => StudentStatus::Applicant, 'email' => '']);

    $this->actingAs($admin)
        ->postJson(portalUrlForAdministrators('/administrators/enrollments/applicants/bulk-action'), [
            'action' => 'notify_approval',
            'ids' => [$withEmail->id, $withoutEmail->id],
        ])
        ->assertOk()
        ->assertJsonPath('processed', 1)
        ->assertJsonPath('skipped', 1);

    Notification::assertSentTo($withEmail, ApplicantApprovedForRequirements::class);
    Notification::assertNotSentTo($withoutEmail, ApplicantApprovedForRequirements::class);
});

it('tags scholarships and converts eligible applicants to enrollments in bulk', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::factory()->create(['lec_per_unit' => 500, 'lab_per_unit' => 600]);
    Subject::factory()->create([
        'course_id' => $course->id,
        'academic_year' => 1,
        'semester' => 1,
        'lecture' => 3,
        'laboratory' => 0,
    ]);

    $a1 = Student::factory()->create(['status' => StudentStatus::Applicant, 'course_id' => $course->id, 'academic_year' => 1]);
    $a2 = Student::factory()->create(['status' => StudentStatus::Applicant, 'course_id' => $course->id, 'academic_year' => 1]);

    $this->actingAs($admin)
        ->postJson(portalUrlForAdministrators('/administrators/enrollments/applicants/bulk-action'), [
            'action' => 'set_scholarship',
            'ids' => [$a1->id, $a2->id],
            'scholarship_type' => ScholarshipType::TES->value,
        ])
        ->assertOk()
        ->assertJsonPath('processed', 2);

    expect($a1->fresh()->scholarship_type)->toBe(ScholarshipType::TES);
    expect($a2->fresh()->scholarship_type)->toBe(ScholarshipType::TES);

    $this->actingAs($admin)
        ->postJson(portalUrlForAdministrators('/administrators/enrollments/applicants/bulk-action'), [
            'action' => 'convert_to_enrollment',
            'ids' => [$a1->id, $a2->id],
        ])
        ->assertOk()
        ->assertJsonPath('processed', 2);

    expect(StudentEnrollment::query()->count())->toBe(2);
    expect(StudentEnrollment::query()->first()->subjectsEnrolled()->count())->toBe(1);
});

it('cancels multiple applicants in bulk with a shared reason', function (): void {
    Notification::fake();

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $a1 = Student::factory()->create(['status' => StudentStatus::Applicant, 'email' => 'cancel1@example.com']);
    $a2 = Student::factory()->create(['status' => StudentStatus::Applicant, 'email' => 'cancel2@example.com']);

    $reason = ApplicantCancellationReason::cases()[0];

    $this->actingAs($admin)
        ->postJson(portalUrlForAdministrators('/administrators/enrollments/applicants/bulk-action'), [
            'action' => 'cancel',
            'ids' => [$a1->id, $a2->id],
            'reason' => $reason->value,
            'note' => 'Incomplete admission requirements after follow-up.',
            'send_email' => false,
        ])
        ->assertOk()
        ->assertJsonPath('processed', 2);

    expect($a1->fresh()->status)->toBe(StudentStatus::Rejected);
    expect($a2->fresh()->status)->toBe(StudentStatus::Rejected);
});
