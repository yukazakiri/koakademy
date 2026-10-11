<?php

declare(strict_types=1);

use App\Email\SystemEmailTemplateStore;
use App\Notifications\ApplicantApprovedForRequirements;
use App\Notifications\ClassScheduleChangedNotification;
use App\Notifications\StatementOfAccountAdjustedNotification;
use App\Notifications\StudentTuitionAdjustedNotification;
use Illuminate\Notifications\AnonymousNotifiable;

function adjustedMailNotifiable(): AnonymousNotifiable
{
    $notifiable = new AnonymousNotifiable;
    $notifiable->routes['mail'] = 'student@example.com';
    $notifiable->name = 'Ana Reyes';

    return $notifiable;
}

afterEach(function (): void {
    // Each test runs in isolation, so drop anything written to the central store.
    app(SystemEmailTemplateStore::class)->forget('class_schedule_changed');
    app(SystemEmailTemplateStore::class)->forget('statement_of_account_adjusted');
    app(SystemEmailTemplateStore::class)->forget('student_tuition_adjusted');
});

it('applies a customized class schedule change email', function (): void {
    $mail = (new ClassScheduleChangedNotification(
        classId: 1,
        classTitle: 'BSCS 101',
        oldSchedule: [],
        newSchedule: ['MWF 8:00 AM'],
        changedByName: 'Registrar',
        customTemplate: [
            'subject' => 'Room change for {{ class_title }}',
            'intro' => 'Hi {{ student_name }}, your section met in a new room.',
        ],
    ))->toMail(adjustedMailNotifiable());

    $rendered = (string) $mail->render();

    expect($mail->subject)->toBe('Room change for BSCS 101')
        ->and($rendered)->toContain('Hi Ana Reyes, your section met in a new room.');
});

it('keeps the schedule diff when a class schedule template replaces the intro', function (): void {
    $mail = (new ClassScheduleChangedNotification(
        classId: 1,
        classTitle: 'BSCS 101',
        oldSchedule: ['MWF 8:00 AM'],
        newSchedule: ['MWF 9:00 AM'],
    ))->toMail(adjustedMailNotifiable());

    $rendered = (string) $mail->render();

    // The default copy still appears because no template was supplied.
    expect($rendered)->toContain('has been updated');
    // The structured diff is always rendered from code.
    expect($rendered)->toContain('Updated schedule')
        ->and($rendered)->toContain('MWF 9:00 AM')
        ->and($rendered)->toContain('Previous schedule');
});

it('applies a customized statement of account email', function (): void {
    $mail = (new StatementOfAccountAdjustedNotification(
        studentId: 1,
        studentName: 'Ana Reyes',
        schoolYear: '2026-2027',
        semester: 1,
        before: ['total_fees' => 20000],
        after: ['total_fees' => 18000],
        adjustmentNote: 'Scholarship discount applied.',
        changedByName: 'Registrar',
        customTemplate: [
            'subject' => 'Your bill changed',
            'custom_body' => 'Hi {{ student_name }}, we adjusted your account for {{ school_year }}.',
            'button_label' => 'See bill',
        ],
    ))->toMail(adjustedMailNotifiable());

    $rendered = (string) $mail->render();

    expect($mail->subject)->toBe('Your bill changed')
        ->and($rendered)->toContain('Hi Ana Reyes, we adjusted your account for 2026-2027.')
        ->and($rendered)->toContain('See bill');
});

it('keeps the adjusted charge lines in a statement of account email', function (): void {
    $mail = (new StatementOfAccountAdjustedNotification(
        studentId: 1,
        studentName: 'Ana Reyes',
        schoolYear: '2026-2027',
        semester: 1,
        before: ['total_fees' => 20000],
        after: ['total_fees' => 18000],
        customTemplate: ['custom_body' => 'Short notice.'],
    ))->toMail(adjustedMailNotifiable());

    $rendered = (string) $mail->render();

    expect($rendered)->toContain('Short notice.')
        ->and($rendered)->toContain('Updated charges');
});

it('applies a customized tuition adjustment email and keeps the money summary', function (): void {
    $snapshotBefore = ['school_year' => '2026-2027', 'semester' => '1', 'total_fees' => 20000, 'paid' => 0, 'credit' => 0, 'balance_due' => 20000];
    $snapshotAfter = ['school_year' => '2026-2027', 'semester' => '1', 'total_fees' => 18000, 'paid' => 0, 'credit' => 0, 'balance_due' => 18000];

    $mail = (new StudentTuitionAdjustedNotification(
        beforeSnapshot: $snapshotBefore,
        afterSnapshot: $snapshotAfter,
        reason: 'Scholarship applied',
        actorName: 'Registrar',
        customTemplate: [
            'subject' => 'Your tuition changed',
            'custom_body' => 'Hi {{ student_name }}, your fees changed for {{ school_year }}.',
        ],
    ))->toMail(adjustedMailNotifiable());

    $rendered = (string) $mail->render();

    expect($mail->subject)->toBe('Your tuition changed')
        ->and($rendered)->toContain('Hi Ana Reyes, your fees changed for 2026-2027.');
    // The financial breakdown is code-rendered, so it survives any template.
    expect($rendered)->toContain('Total fees')
        ->and($rendered)->toContain('Account position');
});

it('renders the built-in default when a system email has no stored template', function (): void {
    $mail = (new ClassScheduleChangedNotification(
        classId: 1,
        classTitle: 'BSCS 101',
        oldSchedule: [],
        newSchedule: ['MWF 8:00 AM'],
    ))->toMail(adjustedMailNotifiable());

    expect($mail->subject)->toBe('Schedule Update: BSCS 101');
});

it('applies an installation-wide tuition template to every recipient', function (): void {
    $snapshotAfter = ['school_year' => '2026-2027', 'semester' => '1', 'total_fees' => 20000, 'paid' => 0, 'credit' => 0, 'balance_due' => 20000];

    app(SystemEmailTemplateStore::class)->put('student_tuition_adjusted', [
        'subject' => 'Finance update for you',
    ]);

    $mail = (new StudentTuitionAdjustedNotification(
        beforeSnapshot: $snapshotAfter,
        afterSnapshot: $snapshotAfter,
        reason: 'Recomputed',
        actorName: null,
    ))->toMail(adjustedMailNotifiable());

    expect($mail->subject)->toBe('Finance update for you');
});

it('applies a customized approved-for-requirements email', function (): void {
    $applicant = App\Models\Student::factory()->create([
        'email' => 'applicant@example.com',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
    ]);

    $mail = (new ApplicantApprovedForRequirements(
        senderName: 'Registrar Office',
        senderRole: 'Registrar',
        customTemplate: [
            'subject' => 'You are approved for requirements',
            'custom_body' => 'Hi {{ applicant_name }}, your application moved forward.',
        ],
    ))->toMail($applicant);

    $rendered = (string) $mail->render();

    expect($mail->subject)->toBe('You are approved for requirements')
        ->and($rendered)->toContain('your application moved forward.');
});

it('renders the default approval copy when no template is supplied', function (): void {
    $applicant = App\Models\Student::factory()->create(['email' => 'applicant2@example.com']);

    $mail = (new ApplicantApprovedForRequirements('Registrar Office', 'Registrar'))
        ->toMail($applicant);

    expect($mail->subject)->toContain('Online Application Approved')
        ->and((string) $mail->render())->toContain('reviewed and approved');
});
