<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Email\EmailTemplateRenderer;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

final class ClassScheduleChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, string>  $oldSchedule
     * @param  array<int, string>  $newSchedule
     */
    public function __construct(
        private readonly int $classId,
        private readonly string $classTitle,
        private readonly array $oldSchedule,
        private readonly array $newSchedule,
        private readonly ?int $changedByUserId = null,
        private readonly ?string $changedByName = null,
        private readonly ?string $subjectTitle = null,
        private readonly ?string $section = null,
        private readonly ?string $facultyName = null,
        private readonly ?Carbon $changedAt = null,
        private readonly array $customTemplate = [],
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof User) {
            return ['database'];
        }

        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $renderer = app(EmailTemplateRenderer::class);
        $tokens = [
            'student_name' => (string) ($notifiable->name ?? $notifiable->email ?? 'Student'),
            'class_title' => $this->classTitle,
            'class_display_name' => $this->classDisplayName(),
            'changed_by' => (string) ($this->changedByName ?? ''),
        ];

        $mail = (new MailMessage)
            ->subject($renderer->subject('class_schedule_changed', $tokens, $this->customTemplate))
            ->greeting($this->greeting($notifiable));

        foreach ($renderer->introLines('class_schedule_changed', $tokens, $this->customTemplate) as $line) {
            $mail->line($line);
        }

        if ($this->contextLine() !== null) {
            $mail->line($this->contextLine());
        }

        $removed = $this->removedLines();
        $added = $this->addedLines();

        if ($removed !== [] || $added !== []) {
            $mail->line('**What changed:**');

            if ($removed !== []) {
                $mail->lines(array_map(
                    fn (string $line): string => '- Removed: '.$line,
                    $removed
                ));
            }

            if ($added !== []) {
                $mail->lines(array_map(
                    fn (string $line): string => '- Added: '.$line,
                    $added
                ));
            }
        }

        $mail->line('**Updated schedule:**')
            ->lines($this->newSchedule === [] ? ['No scheduled meetings.'] : $this->newSchedule)
            ->line('**Previous schedule (for reference):**')
            ->lines($this->oldSchedule === [] ? ['No previous schedule.'] : $this->oldSchedule);

        if ($this->changedByName !== null) {
            $mail->line(sprintf('This change was made by %s.', $this->changedByName));
        }

        return $mail
            ->action('View Class Schedule', $this->actionUrl())
            ->line('Please review the update and adjust your plans accordingly. If you have any questions or conflicts with the new schedule, contact your instructor or the registrar as soon as possible.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Class schedule updated',
            'message' => $this->summaryMessage(),
            'type' => 'class_schedule_changed',
            'priority' => 'high',
            'icon' => 'heroicon-o-calendar-days',
            'class_id' => $this->classId,
            'class_title' => $this->classTitle,
            'subject_title' => $this->subjectTitle,
            'section' => $this->section,
            'faculty_name' => $this->facultyName,
            'old_schedule' => $this->oldSchedule,
            'new_schedule' => $this->newSchedule,
            'removed' => $this->removedLines(),
            'added' => $this->addedLines(),
            'changed_by_user_id' => $this->changedByUserId,
            'changed_by_name' => $this->changedByName,
            'changed_at' => $this->changedAt?->toIso8601String(),
            'action_url' => $this->actionUrl(),
            'action_text' => 'View Class Schedule',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }

    private function greeting(object $notifiable): string
    {
        if ($notifiable instanceof User && $notifiable->name !== '') {
            return sprintf('Hi %s,', explode(' ', $notifiable->name)[0]);
        }

        return 'Hello,';
    }

    private function classDisplayName(): string
    {
        return $this->subjectTitle !== null
            ? sprintf('%s (%s)', $this->subjectTitle, $this->classTitle)
            : $this->classTitle;
    }

    private function contextLine(): ?string
    {
        $parts = array_filter([
            $this->section !== null ? 'Section '.$this->section : null,
            $this->facultyName !== null ? 'Faculty: '.$this->facultyName : null,
        ]);

        return $parts === [] ? null : implode(' • ', $parts);
    }

    /**
     * @return array<int, string>
     */
    private function removedLines(): array
    {
        return array_values(array_diff($this->oldSchedule, $this->newSchedule));
    }

    /**
     * @return array<int, string>
     */
    private function addedLines(): array
    {
        return array_values(array_diff($this->newSchedule, $this->oldSchedule));
    }

    private function summaryMessage(): string
    {
        $added = $this->addedLines();
        $removed = $this->removedLines();

        if ($added !== [] && $removed !== []) {
            return sprintf(
                'The schedule for %s changed from "%s" to "%s".',
                $this->classDisplayName(),
                $removed[0],
                $added[0]
            );
        }

        if ($this->newSchedule === []) {
            return sprintf('The schedule for %s has been cleared. Check the class page for details.', $this->classDisplayName());
        }

        return sprintf('The schedule for %s has changed. It now meets %s.', $this->classDisplayName(), implode('; ', $this->newSchedule));
    }

    private function actionUrl(): string
    {
        return url('/student/classes/'.$this->classId);
    }
}
