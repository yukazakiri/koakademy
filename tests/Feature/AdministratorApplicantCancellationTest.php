<?php

declare(strict_types=1);

use App\Enums\ApplicantCancellationReason;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\ApplicantCancellation;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ApplicantApplicationCancelled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('offers the built in cancellation reasons to the registrar', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators('/administrators/enrollments/applicants'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('cancellation_reasons')
            ->where('cancellation_reasons.0.value', 'incomplete_documents')
            ->where('cancellation_reasons.0.requires_note', false)
            ->etc()
        );

    $reasons = ApplicantCancellationReason::cases();

    expect($reasons)->toContain(ApplicantCancellationReason::Other);
    expect(ApplicantCancellationReason::Other->requiresNote())->toBeTrue();
});

it('closes an application, records the reason and notifies the applicant', function (): void {
    Notification::fake();

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $applicant = Student::factory()->create([
        'status' => StudentStatus::Applicant->value,
        'email' => 'applicant@example.test',
    ]);

    $this->actingAs($admin)
        ->post(portalUrlForAdministrators("/administrators/enrollments/applicants/{$applicant->id}/cancel"), [
            'reason' => ApplicantCancellationReason::IncompleteDocuments->value,
            'note' => 'The birth certificate was never uploaded.',
        ])
        ->assertRedirect();

    $applicant->refresh();

    expect($applicant->status)->toBe(StudentStatus::Rejected);

    $cancellation = ApplicantCancellation::query()->where('student_id', $applicant->id)->firstOrFail();

    expect($cancellation->reason_code)->toBe('incomplete_documents')
        ->and($cancellation->reason_label)->toBe('Incomplete or missing documents')
        ->and($cancellation->note)->toBe('The birth certificate was never uploaded.')
        ->and($cancellation->cancelled_by)->toBe($admin->id)
        ->and($cancellation->email_sent_at)->not->toBeNull()
        ->and($cancellation->email_subject)->toContain('documents still needed')
        ->and($cancellation->email_body)->toContain('The birth certificate was never uploaded.');

    Notification::assertSentTo($applicant, ApplicantApplicationCancelled::class);
});

it('keeps the applicant out of the working queue and lists them as cancelled', function (): void {
    Notification::fake();

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $applicant = Student::factory()->create([
        'status' => StudentStatus::Applicant->value,
        'email' => 'applicant@example.test',
    ]);

    $this->actingAs($admin)
        ->post(portalUrlForAdministrators("/administrators/enrollments/applicants/{$applicant->id}/cancel"), [
            'reason' => ApplicantCancellationReason::ProgramAtCapacity->value,
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators('/administrators/enrollments/applicants'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('applicants.total', 0)
            ->where('stage_counts.cancelled', 1)
            ->where('stage_counts.all', 0)
        );

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators('/administrators/enrollments/applicants?stage=cancelled'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('applicants.total', 1)
            ->where('applicants.data.0.id', $applicant->id)
            ->where('applicants.data.0.stage', 'cancelled')
        );
});

it('requires a note when the reason is other', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $applicant = Student::factory()->create([
        'status' => StudentStatus::Applicant->value,
        'email' => 'applicant@example.test',
    ]);

    $this->actingAs($admin)
        ->post(portalUrlForAdministrators("/administrators/enrollments/applicants/{$applicant->id}/cancel"), [
            'reason' => ApplicantCancellationReason::Other->value,
        ])
        ->assertSessionHasErrors('note');

    $applicant->refresh();

    expect($applicant->status)->toBe(StudentStatus::Applicant)
        ->and(ApplicantCancellation::query()->count())->toBe(0);
});

it('rejects a reason that is not one of the built in options', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $applicant = Student::factory()->create([
        'status' => StudentStatus::Applicant->value,
        'email' => 'applicant@example.test',
    ]);

    $this->actingAs($admin)
        ->post(portalUrlForAdministrators("/administrators/enrollments/applicants/{$applicant->id}/cancel"), [
            'reason' => 'because-i-said-so',
        ])
        ->assertSessionHasErrors('reason');

    $applicant->refresh();

    expect($applicant->status)->toBe(StudentStatus::Applicant);
});

it('previews the email without sending it', function (): void {
    Notification::fake();

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $applicant = Student::factory()->create([
        'status' => StudentStatus::Applicant->value,
        'email' => 'applicant@example.test',
        'first_name' => 'Juan',
        'last_name' => 'Delacruz',
    ]);

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators("/administrators/enrollments/applicants/{$applicant->id}/cancel/preview").'?reason=requirements_not_met&note=The+GWA+is+below+the+cut-off.')
        ->assertOk()
        ->assertJsonPath('reason_label', 'Admission requirements not met')
        ->assertJsonPath('requires_note', false)
        ->assertJsonPath('subject', fn (string $subject): bool => str_contains($subject, 'Update on your application to'))
        ->assertJsonPath('body', fn (string $body): bool => str_contains($body, 'The GWA is below the cut-off.'));

    Notification::assertNothingSent();

    $applicant->refresh();

    expect($applicant->status)->toBe(StudentStatus::Applicant);
});

it('reverses a cancellation and returns the application to the queue', function (): void {
    Notification::fake();

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $applicant = Student::factory()->create([
        'status' => StudentStatus::Applicant->value,
        'email' => 'applicant@example.test',
    ]);

    $this->actingAs($admin)
        ->post(portalUrlForAdministrators("/administrators/enrollments/applicants/{$applicant->id}/cancel"), [
            'reason' => ApplicantCancellationReason::IncompleteDocuments->value,
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(portalUrlForAdministrators("/administrators/enrollments/applicants/{$applicant->id}/cancel/revert"), [
            'reason' => 'Cancelled by mistake.',
        ])
        ->assertRedirect();

    $applicant->refresh();
    $cancellation = ApplicantCancellation::query()->where('student_id', $applicant->id)->firstOrFail();

    expect($applicant->status)->toBe(StudentStatus::Applicant)
        ->and($cancellation->isReverted())->toBeTrue()
        ->and($cancellation->reverted_by)->toBe($admin->id)
        ->and($cancellation->revert_reason)->toBe('Cancelled by mistake.');

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators('/administrators/enrollments/applicants'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('applicants.total', 1)
            ->where('stage_counts.cancelled', 0)
        );
});

it('does not cancel an application that is already closed', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $applicant = Student::factory()->create([
        'status' => StudentStatus::Rejected->value,
        'email' => 'applicant@example.test',
    ]);

    $this->actingAs($admin)
        ->post(portalUrlForAdministrators("/administrators/enrollments/applicants/{$applicant->id}/cancel"), [
            'reason' => ApplicantCancellationReason::IncompleteDocuments->value,
        ])
        ->assertSessionHasErrors('reason');

    expect(ApplicantCancellation::query()->count())->toBe(0);
});
