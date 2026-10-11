<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Email\EmailTemplateRenderer;
use App\Settings\SiteSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class StudentEnrolledVerified extends Notification implements ShouldQueue
{
    use Queueable;

    // public $student_name;

    /**
     * @param  array<string, mixed>  $customTemplate
     */
    public function __construct(public $record, private array $customTemplate = [])
    {
        // $this->student_name = $student_name;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $siteSettings = app(SiteSettings::class)->getBrandingArray();

        $logoUrl = $siteSettings['logo'] ?? null;
        if ($logoUrl && ! str_starts_with((string) $logoUrl, 'http')) {
            $logoUrl = url($logoUrl);
        }

        $studentName = (string) ($this->record->student_name ?? $this->record->student?->full_name ?? 'Student');
        $organizationName = (string) ($siteSettings['organizationName'] ?? config('app.name'));
        $course = $this->record->Course ?? null;

        $tokens = [
            'student_name' => $studentName,
            'program' => (string) ($course?->code ?? $course?->title ?? ''),
            'school_year' => (string) ($this->record->school_year ?? ''),
            'semester' => (string) ($this->record->semester ?? ''),
            'status' => (string) ($this->record->status ?? 'Verified'),
            'step_name' => ucwords(str_replace(['_', '-'], ' ', (string) ($this->record->current_step_key ?? 'Academic Verification'))),
            'portal_url' => (string) config('app.url'),
            'organization_name' => $organizationName,
        ];

        $renderer = app(EmailTemplateRenderer::class);

        return (new MailMessage)
            ->subject($renderer->subject('academic_verified', $tokens, $this->customTemplate))
            ->markdown('emails.enrollment.verified_by_dept_head', [
                'student_name' => $studentName,
                'intro' => $renderer->intro('academic_verified', $tokens, $this->customTemplate),
                'buttonLabel' => $this->customTemplate['button_label'] ?? 'Visit Student Portal',
                'subjects' => $this->record->SubjectsEnrolled,
                'tuition' => $this->record->studentTuition,
                'siteSettings' => $siteSettings,
                'logoUrl' => $logoUrl,
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
