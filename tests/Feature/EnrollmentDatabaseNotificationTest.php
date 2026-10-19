<?php

declare(strict_types=1);

use App\Data\Enrollment\EnrollmentContext;
use App\Enrollment\EnrollmentPolicyRegistry;
use App\Enums\UserRole;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Notifications\EnrollmentDatabaseNotification;
use Illuminate\Support\Facades\Notification;

it('queues an inbox notification to the explicit student user without requiring student email', function (): void {
    Notification::fake();
    $user = User::factory()->create(['role' => UserRole::Student]);
    $student = Student::factory()->create(['user_id' => $user->id, 'email' => null]);
    $enrollment = StudentEnrollment::factory()->create(['student_id' => $student->id, 'school_id' => $user->school_id, 'status' => 'Pending']);

    $result = app(EnrollmentPolicyRegistry::class)->action('enrollment.notify')->execute(
        EnrollmentContext::fromEnrollment($enrollment), ['channel' => 'database', 'notification' => 'status_update'], 'inbox-status',
    );
    $enrollment->update(['status' => 'Verified By Dept Head']);

    expect($result->successful)->toBeTrue();
    Notification::assertSentTo($user, EnrollmentDatabaseNotification::class, function ($notification, $channels) use ($user, $enrollment): bool {
        return $channels === ['database'] && $notification->toDatabase($user)['status'] === 'Pending'
            && $notification->toDatabase($user)['enrollment_id'] === $enrollment->id;
    });
});

it('fails closed instead of guessing an inbox recipient by email', function (): void {
    Notification::fake();
    $user = User::factory()->create(['role' => UserRole::Student]);
    $student = Student::factory()->create(['user_id' => null, 'email' => $user->email]);
    $enrollment = StudentEnrollment::factory()->create(['student_id' => $student->id]);

    $result = app(EnrollmentPolicyRegistry::class)->action('enrollment.notify')->execute(
        EnrollmentContext::fromEnrollment($enrollment), ['channel' => 'database'], 'missing-inbox-recipient',
    );

    expect($result->successful)->toBeFalse();
    Notification::assertNothingSent();
});

it('stores inbox payload through the database channel', function (): void {
    $user = User::factory()->create(['role' => UserRole::Student]);
    $payload = ['title' => 'Enrollment status updated', 'enrollment_id' => 42, 'status' => 'Pending'];

    Notification::sendNow($user, new EnrollmentDatabaseNotification($payload));

    expect($user->notifications()->sole()->data)->toBe($payload);
});

it('rejects staff users and cross-school student recipients', function (bool $staff): void {
    Notification::fake();
    $school = App\Models\School::factory()->create();
    $foreign = App\Models\School::factory()->create();
    $user = User::factory()->create(['role' => $staff ? UserRole::Admin : UserRole::Student, 'school_id' => $staff ? $school->id : $foreign->id]);
    $student = Student::factory()->create(['school_id' => $school->id, 'user_id' => $user->id]);
    $enrollment = StudentEnrollment::factory()->create(['student_id' => $student->id, 'school_id' => $school->id]);

    $result = app(EnrollmentPolicyRegistry::class)->action('enrollment.notify')->execute(EnrollmentContext::fromEnrollment($enrollment), ['channel' => 'database'], 'unsafe-recipient');

    expect($result->successful)->toBeFalse();
    Notification::assertNothingSent();
})->with(['staff user' => true, 'foreign student user' => false]);
