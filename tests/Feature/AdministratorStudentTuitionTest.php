<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Models\User;
use App\Notifications\StatementOfAccountAdjustedNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;

/**
 * @return array<string, float|int|string|null>
 */
function adjustedTuitionPayload(): array
{
    return [
        'total_lectures' => 16000,
        'total_laboratory' => 3000,
        'total_miscelaneous_fees' => 4000,
        'downpayment' => 2500,
        'discount' => 5,
        'adjustment_note' => 'Recalculated laboratory fees.',
    ];
}

beforeEach(function (): void {
    School::factory()->create();

    GeneralSetting::factory()->create([
        'school_starting_date' => '2026-08-01',
        'school_ending_date' => '2027-06-15',
        'semester' => 1,
    ]);
});

it('adjusts tuition fees, syncs enrollment totals, and records adjustment metadata', function (): void {
    Notification::fake();

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create();

    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => (string) $student->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'academic_year' => 1,
        'downpayment' => 2000,
    ]);

    $tuition = StudentTuition::query()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'academic_year' => 1,
        'total_lectures' => 15000,
        'total_laboratory' => 2500,
        'total_miscelaneous_fees' => 3500,
        'discount' => 10,
        'downpayment' => 2000,
        'total_tuition' => 17500,
        'overall_tuition' => 18900,
        'total_balance' => 18900,
        'paid' => 0,
    ]);

    actingAs($admin)
        ->patch(route('administrators.students.update-tuition', $student->id), adjustedTuitionPayload())
        ->assertRedirect()
        ->assertSessionHas('success');

    $tuition->refresh();

    expect($tuition->total_lectures)->toBe(16000.0)
        ->and($tuition->total_laboratory)->toBe(3000.0)
        ->and($tuition->total_miscelaneous_fees)->toBe(4000.0)
        ->and($tuition->total_tuition)->toBe(19000.0)
        ->and(round($tuition->overall_tuition, 2))->toBe(21850.0) // (19000 + 4000) * 0.95
        ->and($tuition->downpayment)->toBe(2500.0)
        ->and($tuition->discount)->toBe(5)
        ->and(round($tuition->total_balance, 2))->toBe(21850.0)
        ->and($tuition->adjustment_note)->toBe('Recalculated laboratory fees.')
        ->and($tuition->adjusted_by_user_id)->toBe($admin->id)
        ->and($tuition->adjusted_at)->not->toBeNull()
        ->and($enrollment->refresh()->downpayment)->toBe(2500.0);
});

it('links a tuition record without an enrollment to the current enrollment', function (): void {
    Notification::fake();

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create();

    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => (string) $student->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'academic_year' => 1,
    ]);

    $tuition = StudentTuition::query()->create([
        'student_id' => $student->id,
        'enrollment_id' => null,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'academic_year' => 1,
    ]);

    actingAs($admin)
        ->patch(route('administrators.students.update-tuition', $student->id), adjustedTuitionPayload())
        ->assertRedirect();

    expect($tuition->refresh()->enrollment_id)->toBe($enrollment->id);
});

it('notifies the linked portal account in-app and by email when fees are adjusted', function (): void {
    Notification::fake();

    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $studentUser = User::factory()->create([
        'role' => UserRole::Student,
        'email' => 'tuition.student@example.test',
    ]);

    $student = Student::factory()->create([
        'email' => 'tuition.student@example.test',
        'user_id' => $studentUser->id,
    ]);

    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => (string) $student->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'academic_year' => 1,
    ]);

    StudentTuition::query()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'academic_year' => 1,
        'total_lectures' => 15000,
        'total_laboratory' => 2500,
        'total_miscelaneous_fees' => 3500,
        'discount' => 10,
        'downpayment' => 2000,
        'total_tuition' => 17500,
        'overall_tuition' => 18900,
        'total_balance' => 18900,
        'paid' => 0,
    ]);

    actingAs($admin)
        ->patch(route('administrators.students.update-tuition', $student->id), adjustedTuitionPayload())
        ->assertRedirect();

    Notification::assertSentTo(
        $studentUser,
        StatementOfAccountAdjustedNotification::class,
        function (StatementOfAccountAdjustedNotification $notification, array $channels) use ($admin): bool {
            $payload = $notification->toArray(new User());

            return $channels === ['mail', 'database']
                && $payload['type'] === 'statement_of_account_adjusted'
                && $payload['school_year'] === '2026 - 2027'
                && $payload['semester'] === 1
                && $payload['before']['total_lectures'] === 15000.0
                && $payload['after']['total_lectures'] === 16000.0
                && round($payload['after']['total_balance'], 2) === 21850.0
                && $payload['adjustment_note'] === 'Recalculated laboratory fees.'
                && $payload['changed_by_user_id'] === $admin->id;
        }
    );

    // No duplicate email is sent when a portal account exists.
    Notification::assertSentOnDemandTimes(StatementOfAccountAdjustedNotification::class, 0);
});

it('falls back to emailing the student address when no portal account is linked', function (): void {
    Notification::fake();

    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $student = Student::factory()->create([
        'email' => 'no-account.student@example.test',
    ]);

    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => (string) $student->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'academic_year' => 1,
    ]);

    StudentTuition::query()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'academic_year' => 1,
    ]);

    actingAs($admin)
        ->patch(route('administrators.students.update-tuition', $student->id), adjustedTuitionPayload())
        ->assertRedirect();

    Notification::assertSentOnDemand(
        StatementOfAccountAdjustedNotification::class,
        fn (StatementOfAccountAdjustedNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $channels === ['mail']
            && ($notifiable->routes['mail'] ?? null) === 'no-account.student@example.test'
    );
});

it('rejects invalid tuition adjustments without changing the record', function (): void {
    Notification::fake();

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create();

    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => (string) $student->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'academic_year' => 1,
    ]);

    $tuition = StudentTuition::query()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'academic_year' => 1,
        'discount' => 10,
    ]);

    actingAs($admin)
        ->patch(route('administrators.students.update-tuition', $student->id), [
            ...adjustedTuitionPayload(),
            'discount' => 150,
        ])
        ->assertSessionHasErrors('discount');

    expect($tuition->refresh()->discount)->toBe(10);

    Notification::assertNothingSent();
});
