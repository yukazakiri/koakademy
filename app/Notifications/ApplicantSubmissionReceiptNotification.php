<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Settings\SiteSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ApplicantSubmissionReceiptNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $applicantId,
        public readonly string $applicantName,
        public readonly ?string $programName = null,
        public readonly ?string $schoolYear = null,
        public readonly ?string $semesterLabel = null,
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
        $settings = app(SiteSettings::class);
        $appName = $settings->getAppName();
        $organizationName = $settings->getOrganizationName();
        $email = (string) ($notifiable->email ?? '');

        $query = array_filter([
            'email' => $email !== '' ? $email : null,
            'reference_id' => $this->applicantId,
        ]);
        $statusUrl = url('/enrollment/status').($query !== [] ? '?'.http_build_query($query) : '');

        $message = (new MailMessage)
            ->subject("{$appName} — Application Received (Ref: {$this->applicantId})")
            ->greeting("Hello {$this->applicantName},")
            ->line("Thank you for submitting your online enrollment application to {$organizationName}. Keep your reference number below to track your application progress.")
            ->line("**Reference / Applicant ID:** {$this->applicantId}");

        if (filled($this->programName)) {
            $message->line("**Program Applied For:** {$this->programName}");
        }

        if (filled($this->schoolYear) || filled($this->semesterLabel)) {
            $term = mb_trim(implode(' · ', array_filter([$this->schoolYear, $this->semesterLabel])));
            if ($term !== '') {
                $message->line("**Academic Term:** {$term}");
            }
        }

        return $message
            ->action('Track Application Status', $statusUrl)
            ->line('Our admissions and registrar team will review your submission and notify you by email once your application advances to the next stage.');
    }
}
