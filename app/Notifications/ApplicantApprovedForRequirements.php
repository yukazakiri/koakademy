<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Email\EmailTemplateRenderer;
use App\Models\Student;
use App\Settings\SiteSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ApplicantApprovedForRequirements extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $customTemplate
     */
    public function __construct(
        private readonly string $senderName,
        private readonly string $senderRole,
        private readonly array $customTemplate = [],
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $siteSettings = app(SiteSettings::class)->getBrandingArray();

        $logoUrl = $siteSettings['logo'] ?? null;
        if ($logoUrl && ! str_starts_with((string) $logoUrl, 'http')) {
            $logoUrl = url($logoUrl);
        }

        $renderer = app(EmailTemplateRenderer::class);
        $course = $notifiable instanceof Student ? $notifiable->Course : null;
        $departmentCode = $course?->department?->code;

        $tokens = [
            'applicant_name' => (string) ($notifiable->full_name ?? $notifiable->first_name ?? 'Applicant'),
            'program' => (string) ($course?->code ?? ''),
            'department' => (string) ($departmentCode ?? ''),
            'organization_name' => (string) ($siteSettings['organizationName'] ?? config('app.name')),
            'sender_name' => $this->senderName,
        ];

        return (new MailMessage)
            ->subject($renderer->subject('applicant_approved_for_requirements', $tokens, $this->customTemplate))
            ->markdown('emails.enrollment.applicant-approved', [
                'studentName' => $notifiable->full_name ?? $notifiable->first_name ?? 'Applicant',
                'intro' => $renderer->intro('applicant_approved_for_requirements', $tokens, $this->customTemplate),
                'program' => $course?->code,
                'department' => $departmentCode,
                'siteSettings' => $siteSettings,
                'logoUrl' => $logoUrl,
                'senderName' => $this->senderName,
                'senderRole' => $this->senderRole,
            ]);
    }
}
