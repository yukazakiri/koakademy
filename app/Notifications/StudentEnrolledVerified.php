<?php

declare(strict_types=1);

namespace App\Notifications;

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
        return (new MailMessage)
            ->subject('Enrollment Verified - Next Steps')
            ->markdown('emails.enrollment.verified_by_dept_head', [
                'student_name' => $this->record->student_name,
                'subjects' => $this->record->SubjectsEnrolled,
                'tuition' => $this->record->studentTuition,
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
