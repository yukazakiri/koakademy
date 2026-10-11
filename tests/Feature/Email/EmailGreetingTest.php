<?php

declare(strict_types=1);

use App\Email\EmailTemplateCatalog;
use App\Email\EmailTemplateRenderer;
use App\Notifications\EnrollmentStatusUpdatedNotification;
use App\Notifications\MigrateToStudent;
use App\Notifications\StudentEnrolledVerified;
use Illuminate\Notifications\AnonymousNotifiable;

/**
 * Every enrollment blade renders its own "Dear <student>," greeting, so the
 * customizable intro must never repeat one. A duplicated greeting made the admin
 * preview disagree with the email applicants actually received.
 */
it('never greets twice in a status update email', function (): void {
    $student = App\Models\Student::factory()->create([
        'email' => 'greet@example.com',
        'first_name' => 'Dana',
        'last_name' => 'Reyes',
    ]);
    $enrollment = App\Models\StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'status' => 'Interview Scheduled',
        'current_step_key' => 'entrance_exam',
    ]);

    $mail = (new EnrollmentStatusUpdatedNotification($enrollment->fresh()))
        ->toMail(new AnonymousNotifiable);

    expect($mail->subject)->toContain('Enrollment Status Update');

    $rendered = (string) $mail->render();

    expect(mb_substr_count($rendered, 'Dear '.$student->full_name))->toBe(1);
    expect(mb_stripos($rendered, 'Hello '.$student->full_name))->toBeFalse();
});

it('never greets twice when a custom template is supplied', function (): void {
    $student = App\Models\Student::factory()->create([
        'email' => 'greet2@example.com',
        'first_name' => 'Evan',
        'last_name' => 'Cruz',
    ]);
    $enrollment = App\Models\StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'status' => 'Document Review',
        'current_step_key' => 'document_review',
    ]);

    $mail = (new EnrollmentStatusUpdatedNotification($enrollment->fresh(), customTemplate: [
        'notification' => 'status_update',
        'subject' => 'Update for you',
        'custom_body' => 'Your file moved forward.',
    ]))->toMail(new AnonymousNotifiable);

    $rendered = (string) $mail->render();

    expect(mb_substr_count($rendered, 'Dear '.$student->full_name))->toBe(1);
});

it('greets once for the academic clearance email', function (): void {
    $student = App\Models\Student::factory()->create([
        'email' => 'greet3@example.com',
        'first_name' => 'Fiona',
        'last_name' => 'Mercado',
    ]);
    $enrollment = App\Models\StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'status' => 'Verified By Dept Head',
        'current_step_key' => 'academic_verified',
    ]);

    $rendered = (string) (new StudentEnrolledVerified($enrollment->fresh()))
        ->toMail(new AnonymousNotifiable)
        ->render();

    expect(mb_substr_count($rendered, 'Dear '.$student->full_name))->toBe(1);
    expect(mb_stripos($rendered, 'Hello '.$student->full_name))->toBeFalse();
});

it('greets once for the assessment certificate email', function (): void {
    $student = App\Models\Student::factory()->create([
        'email' => 'greet4@example.com',
        'first_name' => 'Greg',
        'last_name' => 'Dizon',
    ]);
    $enrollment = App\Models\StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'status' => 'Enrolled',
        'current_step_key' => 'completed',
    ]);

    $rendered = (string) (new MigrateToStudent($enrollment->fresh(), requiresAttachment: false))
        ->toMail(new AnonymousNotifiable)
        ->render();

    expect(mb_substr_count($rendered, 'Dear '.$student->full_name))->toBe(1);
    expect(mb_stripos($rendered, 'Hello '.$student->full_name))->toBeFalse();
});

it('keeps the phrase congratulations without repeating the greeting', function (): void {
    $student = App\Models\Student::factory()->create(['email' => 'greet5@example.com']);
    $enrollment = App\Models\StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'status' => 'Enrolled',
        'current_step_key' => 'completed',
    ]);

    $rendered = (string) (new MigrateToStudent($enrollment->fresh(), requiresAttachment: false))
        ->toMail(new AnonymousNotifiable)
        ->render();

    expect($rendered)->toContain('Congratulations')
        ->and(mb_substr_count($rendered, 'Dear '.$student->full_name))->toBe(1);
});

it('does not open any enrollment default with a greeting', function (): void {
    foreach (EmailTemplateCatalog::ENROLLMENT_KEYS as $key) {
        $intro = EmailTemplateCatalog::defaultFor($key)['intro'] ?? '';

        expect(mb_stripos($intro, 'dear {{ student_name }}'))->toBeFalse("{$key} intro repeats the greeting");
        expect(mb_stripos($intro, 'hello {{ student_name }}'))->toBeFalse("{$key} intro repeats the greeting");
        expect(mb_stripos($intro, 'congratulations {{ student_name }}'))->toBeFalse("{$key} intro repeats the greeting");
    }
});

it('strips a salutation left over from an older preset', function (string $stored): void {
    expect(EmailTemplateCatalog::stripSalutation($stored))->not->toMatch('/^\s*(hello|hi|dear|greetings|congratulations)\s*\{\{\s*student_name\s*\}\}/i');
})->with([
    'hello' => 'Hello {{ student_name }},\n\nYour application moved forward.',
    'dear' => 'Dear {{ student_name }},\n\nYour application moved forward.',
    'congratulations' => 'Congratulations {{ student_name }}!\n\nYou are enrolled.',
    'no spaces' => 'Dear {{student_name}}, Your application moved forward.',
    'padded' => '   Hello {{ student_name }},   \n\nYour application moved forward.',
]);

it('leaves body copy that never had a salutation untouched', function (): void {
    $body = 'Your application moved forward. Please review it.';

    expect(EmailTemplateCatalog::stripSalutation($body))->toBe($body);

    // A literal name must never be rewritten — only the student_name token.
    expect(EmailTemplateCatalog::stripSalutation('Hello Maria Santos, welcome.'))->toBe('Hello Maria Santos, welcome.');
});

it('renders a stored salutation-free intro without doubling the greeting', function (): void {
    $student = App\Models\Student::factory()->create(['email' => 'legacy@example.com']);
    $enrollment = App\Models\StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'status' => 'Interview Scheduled',
        'current_step_key' => 'entrance_exam',
    ]);

    // Exactly the shape the normalization migration repairs.
    $mail = (new EnrollmentStatusUpdatedNotification($enrollment->fresh(), customTemplate: [
        'notification' => 'status_update',
        'custom_body' => EmailTemplateCatalog::stripSalutation(
            "Hello {{ student_name }},\n\nYour application moved forward."
        ),
    ]))->toMail(new AnonymousNotifiable);

    $rendered = (string) $mail->render();

    expect(mb_substr_count($rendered, 'Dear '.$student->full_name))->toBe(1)
        ->and($rendered)->toContain('Your application moved forward.');
});

it('interpolates the intro without repeating the student name', function (): void {
    $renderer = app(EmailTemplateRenderer::class);
    $tokens = ['student_name' => 'Dana Reyes', 'organization_name' => 'KoAkademy', 'status' => 'Cleared'];

    foreach (EmailTemplateCatalog::ENROLLMENT_KEYS as $key) {
        $intro = $renderer->intro($key, $tokens);

        expect(mb_substr_count($intro, 'Dana Reyes'))->toBeLessThanOrEqual(1, "{$key} names the student more than once in the greeting");
    }
});
