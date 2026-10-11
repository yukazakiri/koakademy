<?php

declare(strict_types=1);

use App\Notifications\TimetablePdfFailedNotification;
use App\Notifications\TimetablePdfGeneratedNotification;
use Illuminate\Notifications\AnonymousNotifiable;

function timetableNotifiable(): AnonymousNotifiable
{
    $notifiable = new AnonymousNotifiable;
    $notifiable->routes['mail'] = 'student@example.com';
    $notifiable->name = 'Ana';

    return $notifiable;
}

it('renders the built-in default subject for a generated timetable', function (): void {
    $mail = (new TimetablePdfGeneratedNotification('timetable.pdf', 'Ana Reyes', '/download/timetable'))
        ->toMail(timetableNotifiable());

    expect($mail->subject)->toBe('Timetable PDF Generated Successfully')
        ->and((string) $mail->render())
        ->toContain('The timetable PDF for Ana Reyes has been generated successfully.');
});

it('applies a custom template to the generated timetable email', function (): void {
    $mail = (new TimetablePdfGeneratedNotification(
        'timetable.pdf',
        'Ana Reyes',
        '/download/timetable',
        [
            'subject' => 'Your schedule for {{ student_name }} is ready',
            'custom_body' => 'Hi {{ student_name }}, your timetable is attached.',
            'button_label' => 'Grab it',
        ]
    ))->toMail(timetableNotifiable());

    $rendered = (string) $mail->render();

    expect($mail->subject)->toBe('Your schedule for Ana Reyes is ready')
        ->and($rendered)->toContain('Hi Ana Reyes, your timetable is attached.')
        ->and($rendered)->toContain('Grab it');
});

it('applies a custom template to a failed timetable email', function (): void {
    $mail = (new TimetablePdfFailedNotification(
        'Ana Reyes',
        'Template not found',
        [
            'subject' => 'We could not build your timetable',
            'custom_body' => 'Sorry {{ student_name }}, the generation failed: {{ error_message }}.',
        ]
    ))->toMail(timetableNotifiable());

    $rendered = (string) $mail->render();

    expect($mail->subject)->toBe('We could not build your timetable')
        ->and($rendered)->toContain('Sorry Ana Reyes, the generation failed: Template not found.');
});

it('still renders the default failure copy without a template', function (): void {
    $mail = (new TimetablePdfFailedNotification('Ana Reyes', 'Template not found'))
        ->toMail(timetableNotifiable());

    expect($mail->subject)->toBe('Timetable PDF Generation Failed')
        ->and((string) $mail->render())->toContain('There was an error generating the timetable PDF for Ana Reyes.');
});
