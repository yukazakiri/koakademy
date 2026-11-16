<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\GradeRelease;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Throwable;

final class StudentGradesPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $year,
        private readonly int $semester,
        private readonly string $channel,
        private readonly ?int $releaseId = null,
        private readonly ?int $revision = null,
    ) {
        $this->afterCommit();
    }

    public function failed(Throwable $exception): void
    {
        report($exception);

        if ($this->releaseId === null || $this->revision === null) {
            return;
        }

        DB::transaction(function (): void {
            $release = GradeRelease::query()
                ->whereKey($this->releaseId)
                ->where('revision', $this->revision)
                ->lockForUpdate()
                ->first();

            if (! $release instanceof GradeRelease) {
                return;
            }

            $status = is_array($release->notification_status)
                ? $release->notification_status
                : ['mail' => 'disabled', 'database' => 'disabled', 'warnings' => []];

            $status[$this->channel] = 'failed';
            $warnings = is_array($status['warnings'] ?? null) ? $status['warnings'] : [];
            $warning = $this->channel === 'mail'
                ? 'The email notification delivery failed.'
                : 'The in-app notification delivery failed.';
            if (! in_array($warning, $warnings, true)) {
                $warnings[] = $warning;
            }
            $status['warnings'] = $warnings;

            $release->update(['notification_status' => $status]);
        });
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
