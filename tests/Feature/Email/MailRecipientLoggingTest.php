<?php

declare(strict_types=1);

use App\Models\Student;
use App\Models\StudentTuitionUpdateRequest;
use App\Models\User;
use App\Notifications\StudentTuitionUpdateRequestReviewedNotification;
use Backstage\Mails\Laravel\Models\Mail as MailLog;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

it('synchronizes normalized recipients when mail is logged and updated', function (): void {
    $mail = MailLog::factory()->create([
        'to' => ['Student@Example.com' => 'Ana Reyes'],
        'cc' => ['Student@Example.com' => 'Duplicate', 'Finance@Example.com' => 'Finance'],
        'bcc' => ['Audit@Example.org' => null],
    ]);

    $this->assertDatabaseCount('mail_recipients', 3);
    $this->assertDatabaseHas('mail_recipients', [
        'mail_id' => $mail->id, 'email' => 'student@example.com',
        'name' => 'ana reyes', 'domain' => 'example.com',
    ]);
    $this->assertDatabaseHas('mail_recipients', ['mail_id' => $mail->id, 'email' => 'finance@example.com']);
    $this->assertDatabaseHas('mail_recipients', ['mail_id' => $mail->id, 'email' => 'audit@example.org', 'name' => null]);

    $mail->update(['to' => ['New@Example.net' => 'New Student'], 'cc' => null, 'bcc' => null]);

    $this->assertDatabaseCount('mail_recipients', 1);
    $this->assertDatabaseHas('mail_recipients', [
        'mail_id' => $mail->id, 'email' => 'new@example.net',
        'name' => 'new student', 'domain' => 'example.net',
    ]);
});

it('backfills existing mail recipients when upgrading and supports rollback', function (): void {
    $migration = require database_path('migrations/2026_10_14_133059_create_mail_recipients_table.php');
    $migration->down();
    $mail = MailLog::withoutEvents(fn () => MailLog::factory()->create([
        'to' => [' Student@Example.com ' => ' Ana Reyes ', '' => 'Empty'],
        'cc' => ['student@example.com' => 'Duplicate', 'Finance@Example.com' => 'Finance'],
        'bcc' => ['Audit@Example.org' => null],
    ]));
    MailLog::withoutEvents(fn () => MailLog::factory()->create(['to' => null, 'cc' => null, 'bcc' => null]));

    $migration->up();

    $this->assertDatabaseCount('mail_recipients', 3);
    $this->assertDatabaseHas('mail_recipients', [
        'mail_id' => $mail->id, 'email' => 'student@example.com',
        'name' => 'ana reyes', 'domain' => 'example.com',
    ]);
    $this->assertDatabaseHas('mail_recipients', ['mail_id' => $mail->id, 'email' => 'finance@example.com']);
    $this->assertDatabaseHas('mail_recipients', ['mail_id' => $mail->id, 'email' => 'audit@example.org', 'name' => null]);
});

it('sends the queued tuition review mail and logs its recipient', function (string $status): void {
    config([
        'mail.default' => 'array',
        'mail.mailers.array' => ['transport' => 'array'],
        'mail.from.address' => 'school@example.com',
        'mails.logging.enabled' => true,
    ]);
    $user = User::factory()->create(['email' => 'student@example.com']);
    $student = Student::factory()->create(['user_id' => $user->id]);
    $request = StudentTuitionUpdateRequest::query()->create([
        'submitted_by_user_id' => $user->id,
        'student_id' => $student->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'concern_type' => StudentTuitionUpdateRequest::ConcernOther,
        'details' => 'Please review my tuition balance.',
        'status' => $status,
    ]);
    Queue::fake([SendQueuedNotifications::class]);

    Notification::send($user, new StudentTuitionUpdateRequestReviewedNotification($request));

    Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool => $job->notification instanceof StudentTuitionUpdateRequestReviewedNotification
        && $job->channels === ['mail']
        && $job->notifiables->sole()->is($user));
    $queued = Queue::pushed(SendQueuedNotifications::class)
        ->first(fn (SendQueuedNotifications $job): bool => $job->channels === ['mail']);

    // Exercise worker deserialization and the real mail listeners. Mail::fake()
    // would bypass the logging failure this regression must catch.
    $job = unserialize(serialize($queued));
    $job->handle(app(ChannelManager::class));

    $transport = Mail::mailer('array')->getSymfonyTransport();
    expect($transport)->toBeInstanceOf(ArrayTransport::class);
    expect($transport->messages())->toHaveCount(1);
    $mail = MailLog::query()->sole();
    expect($mail->subject)->toContain("Tuition Update Request #{$request->id}");
    $this->assertDatabaseHas('mail_recipients', [
        'mail_id' => $mail->id, 'email' => 'student@example.com', 'domain' => 'example.com',
    ]);
})->with([
    'resolved' => StudentTuitionUpdateRequest::StatusResolved,
    'rejected' => StudentTuitionUpdateRequest::StatusRejected,
]);
