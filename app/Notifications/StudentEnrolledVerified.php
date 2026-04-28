<?php

declare(strict_types=1);

namespace App\Notifications;

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
     * Create a new notification instance.
     */
    public function __construct(public $record)
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

        return (new MailMessage)
            ->subject(sprintf(
                'Enrollment Verified - Next Steps | %s',
                $siteSettings['organizationName'] ?? config('app.name')
            ))
            ->markdown('emails.enrollment.verified_by_dept_head', [
                'student_name' => $this->record->student_name,
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
