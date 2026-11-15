<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class StudentGradesPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $year, private readonly int $semester, private readonly string $channel)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return [$this->channel];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your grades are available')
            ->line("Your grades for {$this->year}, semester {$this->semester}, have been published or updated.")
            ->line('Sign in to the student portal to review them.')
            ->action('View grades', route('student.classes.index'));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Your grades are available',
            'message' => "Grades for {$this->year}, semester {$this->semester}, have been published or updated.",
            'type' => 'grades_published',
            'action_url' => route('student.classes.index'),
            'action_text' => 'View grades',
        ];
    }
}
