<?php

declare(strict_types=1);

use App\Email\EmailTemplateCatalog;
use App\Email\EmailTemplateRenderer;
use App\Notifications\StudentEnrolledVerified;
use Illuminate\Notifications\AnonymousNotifiable;

function emailTestNotifiable(): AnonymousNotifiable
{
    $notifiable = new AnonymousNotifiable;
    $notifiable->routes['mail'] = 'student@example.com';
    $notifiable->name = 'Ana';

    return $notifiable;
}

it('falls back to the built-in catalog default when no override is stored', function (): void {
    $renderer = app(EmailTemplateRenderer::class);

    $subject = $renderer->subject('status_update', ['status' => 'Cleared', 'organization_name' => 'KoAkademy']);
    expect($subject)->toBe('Enrollment Status Update: Cleared | KoAkademy');

    $intro = $renderer->intro('status_update', ['student_name' => 'Maria Santos']);
    expect($intro)->toContain('Hello Maria Santos,');
});

it('prefers a stored override over the built-in default', function (): void {
    $renderer = app(EmailTemplateRenderer::class);

    $subject = $renderer->subject('status_update', ['organization_name' => 'KoAkademy'], [
        'subject' => 'Custom subject for {{ organization_name }}',
    ]);

    expect($subject)->toBe('Custom subject for KoAkademy')
        ->and($renderer->intro('status_update', [], ['custom_body' => 'Totally different body.']))
        ->toBe('Totally different body.');
});

it('leaves a token untouched when the caller supplies no value for it', function (): void {
    $renderer = app(EmailTemplateRenderer::class);

    expect($renderer->subject('status_update', [], ['subject' => 'Hi {{ organization_name }}']))
        ->toBe('Hi {{ organization_name }}');
});

it('interpolates both token spellings', function (): void {
    $renderer = app(EmailTemplateRenderer::class);

    expect($renderer->interpolate('Hi {{ name }} / {{name}}', ['name' => 'Ana']))
        ->toBe('Hi Ana / Ana');
});

it('applies an installation-wide override to a system email', function (): void {
    app(App\Email\SystemEmailTemplateStore::class)->put('timetable_pdf_generated', [
        'subject' => 'Central subject for {{ student_name }}',
        'custom_body' => 'Central body for {{ student_name }}.',
    ]);

    $mail = (new App\Notifications\TimetablePdfGeneratedNotification('t.pdf', 'Ana Reyes', '/download'))
        ->toMail(emailTestNotifiable());

    expect($mail->subject)->toBe('Central subject for Ana Reyes')
        ->and((string) $mail->render())->toContain('Central body for Ana Reyes.');
});

it('reverts a system email to its built-in default when the override is removed', function (): void {
    $store = app(App\Email\SystemEmailTemplateStore::class);

    $store->put('timetable_pdf_generated', ['subject' => 'Temporary subject']);
    expect(
        (new App\Notifications\TimetablePdfGeneratedNotification('t.pdf', 'Ana Reyes', '/download'))
            ->toMail(emailTestNotifiable())->subject
    )->toBe('Temporary subject');

    $store->forget('timetable_pdf_generated');

    expect(
        (new App\Notifications\TimetablePdfGeneratedNotification('t.pdf', 'Ana Reyes', '/download'))
            ->toMail(emailTestNotifiable())->subject
    )->toBe('Timetable PDF Generated Successfully');
});

it('never reads an installation-wide override for an enrollment template', function (): void {
    // Enrollment templates are scoped per workflow step on the policy draft, so a
    // central override must never leak into them.
    app(App\Email\SystemEmailTemplateStore::class)->put('status_update', [
        'subject' => 'CENTRAL OVERRIDE',
    ]);

    $renderer = app(EmailTemplateRenderer::class);

    expect($renderer->subject('status_update', ['status' => 'X', 'organization_name' => 'Y']))
        ->toBe('Enrollment Status Update: X | Y');
});

it('keeps auth email out of the template catalog', function (): void {
    $keys = EmailTemplateCatalog::keys();

    foreach (['two_factor_code', 'password_reset', 'magic_login_link', 'signup_otp', 'email_authentication_code'] as $authKey) {
        expect($keys)->not->toContain($authKey);
        expect(EmailTemplateCatalog::defaultFor($authKey))->toBeNull();
    }
});

it('lists every template-driven email with a subject, intro and tokens', function (): void {
    foreach (EmailTemplateCatalog::all() as $key => $definition) {
        expect($definition['subject'])->not->toBeEmpty("{$key} is missing a subject");
        expect($definition['intro'])->not->toBeEmpty("{$key} is missing intro copy");
        expect($definition['tokens'])->not->toBeEmpty("{$key} is missing tokens");
    }

    expect(EmailTemplateCatalog::keys())
        ->toContain(EmailTemplateCatalog::ENROLLMENT_KEYS[0], EmailTemplateCatalog::ENROLLMENT_KEYS[1], EmailTemplateCatalog::ENROLLMENT_KEYS[2], EmailTemplateCatalog::ENROLLMENT_KEYS[3])
        ->toContain(EmailTemplateCatalog::SYSTEM_KEYS[0]);
});

it('every token referenced by a default template is declared for that email', function (): void {
    $undeclared = [];

    foreach (EmailTemplateCatalog::all() as $key => $definition) {
        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $definition['subject'].' '.$definition['intro'], $matches);

        foreach (array_unique($matches[1]) as $token) {
            if (! array_key_exists($token, $definition['tokens'])) {
                $undeclared[] = "{$key}.{$token}";
            }
        }
    }

    expect($undeclared)->toBe([]);
});

it('applies a custom academic_verified template to the rendered email', function (): void {
    Illuminate\Support\Facades\Notification::fake();

    $student = App\Models\Student::factory()->create(['first_name' => 'Dana', 'email' => 'dana@example.com']);
    $enrollment = App\Models\StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'status' => 'Verified By Dept Head',
        'current_step_key' => 'academic_verified',
    ]);

    $notification = new StudentEnrolledVerified($enrollment->fresh(), [
        'subject' => 'CLEARED: {{ student_name }} is ready to pay',
        'custom_body' => 'Hi {{ student_name }}, clearance is done.',
    ]);

    $mail = $notification->toMail(new AnonymousNotifiable);
    $rendered = (string) $mail->render();

    // StudentEnrollment::student_name is the student's full name.
    $expectedName = $student->full_name;

    expect($mail->subject)->toBe("CLEARED: {$expectedName} is ready to pay")
        ->and($rendered)->toContain("Hi {$expectedName}, clearance is done.");
});

it('keeps the structured approval tables when a template replaces the intro', function (): void {
    $student = App\Models\Student::factory()->create(['first_name' => 'Evan', 'email' => 'evan@example.com']);
    $enrollment = App\Models\StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'status' => 'Verified By Dept Head',
        'current_step_key' => 'academic_verified',
    ]);

    $mail = (new StudentEnrolledVerified($enrollment->fresh(), [
        'custom_body' => 'Short custom intro.',
    ]))->toMail(new AnonymousNotifiable);

    $rendered = (string) $mail->render();

    // The templatable prose changes...
    expect($rendered)->toContain('Short custom intro.');
    // ...but the data a student is entitled to see is still rendered from code.
    expect($rendered)->toContain('Approved Subjects')
        ->and($rendered)->not->toContain('We are pleased to inform you that your subject enrollment');
});
