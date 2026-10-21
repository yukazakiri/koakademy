<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Email\EmailTemplateRenderer;
use App\Email\SystemEmailTemplateStore;
use App\Enums\ApplicantCancellationReason;
use App\Models\Student;
use App\Settings\SiteSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells an applicant that their application was closed and why.
 *
 * The subject and body are composed by the registrar in the cancellation
 * dialog, so the copy that arrives is exactly the copy that was previewed.
 * The template key still allows an installation to override the surrounding
 * wording, subject and button label.
 */
final class ApplicantApplicationCancelled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ApplicantCancellationReason $reason,
        private readonly string $reasonLabel,
        private readonly ?string $note,
        private readonly string $subject,
        private readonly string $body,
        private readonly string $senderName,
        private readonly string $senderRole,
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
            'applicant_name' => (string) ($notifiable instanceof Student ? mb_trim($notifiable->first_name.' '.$notifiable->last_name) : 'Applicant'),
            'program' => (string) ($course?->code ?? ''),
            'department' => (string) ($departmentCode ?? ''),
            'reason' => $this->reasonLabel,
            'reason_detail' => (string) ($this->note ?? ''),
            'organization_name' => (string) ($siteSettings['organizationName'] ?? config('app.name')),
            'sender_name' => $this->senderName,
        ];

        // An installation that edited the template wins over the reason specific
        // copy, which in turn wins over the catalog default.
        $stored = app(SystemEmailTemplateStore::class)->get('applicant_cancelled');

        $subject = filled($stored['subject'] ?? null)
            ? $renderer->subject('applicant_cancelled', $tokens)
            : $this->subject;

        $intro = filled($stored['intro'] ?? null)
            ? $renderer->intro('applicant_cancelled', $tokens)
            : $this->body;

        return (new MailMessage)
            ->subject($subject)
            ->markdown('emails.enrollment.applicant-cancelled', [
                'studentName' => $tokens['applicant_name'],
                'intro' => $intro,
                'reasonLabel' => $this->reasonLabel,
                'note' => $this->note,
                'program' => $course?->code,
                'department' => $departmentCode,
                'siteSettings' => $siteSettings,
                'logoUrl' => $logoUrl,
                'senderName' => $this->senderName,
                'senderRole' => $this->senderRole,
            ]);
    }
}
