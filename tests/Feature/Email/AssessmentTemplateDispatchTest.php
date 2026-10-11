<?php

declare(strict_types=1);

use App\Jobs\SendAssessmentNotificationJob;
use App\Notifications\MigrateToStudent;

/**
 * The `assessment` template is queued through a job, so the configuration has to
 * survive the queue boundary. The job is exercised without running PDF generation
 * (which needs the Chromium microservice) by inspecting what it forwards.
 */
it('forwards the assessment template through the queued job', function (): void {
    $student = App\Models\Student::factory()->create(['email' => 'assessment@example.com']);
    $enrollment = App\Models\StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'status' => 'Enrolled',
        'current_step_key' => 'completed',
    ]);

    $template = [
        'subject' => 'Welcome aboard, {{ student_name }}!',
        'custom_body' => 'Your matriculation is confirmed, {{ student_name }}.',
    ];

    $job = new SendAssessmentNotificationJob($enrollment->fresh(), 'assessment-template-test', $template);

    // The job stores the template verbatim so it can reach the notification after
    // the queue round-trip.
    $reflected = (new ReflectionClass($job))->getProperty('customTemplate');
    $reflected->setAccessible(true);

    expect($reflected->getValue($job))->toBe($template);

    // And the notification it builds renders that template.
    $notification = new MigrateToStudent(
        $enrollment->fresh(),
        requiresAttachment: false,
        customTemplate: $template,
    );

    $mail = $notification->toMail(new Illuminate\Notifications\AnonymousNotifiable);
    $studentName = $student->full_name;

    expect($mail->subject)->toBe("Welcome aboard, {$studentName}!")
        ->and((string) $mail->render())->toContain("Your matriculation is confirmed, {$studentName}.");
});

it('keeps the enrollment details table when a template replaces the assessment intro', function (): void {
    $student = App\Models\Student::factory()->create(['email' => 'table@example.com']);
    $enrollment = App\Models\StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'status' => 'Enrolled',
        'current_step_key' => 'completed',
    ]);

    $mail = (new MigrateToStudent(
        $enrollment->fresh(),
        requiresAttachment: false,
        customTemplate: ['custom_body' => 'A short custom congratulation.'],
    ))->toMail(new Illuminate\Notifications\AnonymousNotifiable);

    $rendered = (string) $mail->render();

    expect($rendered)->toContain('A short custom congratulation.')
        ->and($rendered)->toContain('Your Enrollment Details');
});

it('renders the built-in assessment default when no template was stored', function (): void {
    $student = App\Models\Student::factory()->create(['email' => 'default@example.com']);
    $enrollment = App\Models\StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'status' => 'Enrolled',
        'current_step_key' => 'completed',
    ]);

    $mail = (new MigrateToStudent($enrollment->fresh(), requiresAttachment: false))
        ->toMail(new Illuminate\Notifications\AnonymousNotifiable);

    expect($mail->subject)->toBe('Enrollment Confirmed & Assessment Certificate | '.config('app.name'));
});
