<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Email\EmailTemplateRenderer;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TimetablePdfFailedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param  array<string, mixed>  $customTemplate
     */
    public function __construct(
        private string $studentName,
        private string $errorMessage,
        private array $customTemplate = []
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $renderer = app(EmailTemplateRenderer::class);
        $tokens = [
            'student_name' => $this->studentName,
            'error_message' => $this->errorMessage,
        ];

        $mail = (new MailMessage)
            ->subject($renderer->subject('timetable_pdf_failed', $tokens, $this->customTemplate))
            ->greeting('Hello '.$notifiable->name.'!');

        foreach ($renderer->introLines('timetable_pdf_failed', $tokens, $this->customTemplate) as $line) {
            $mail->line($line);
        }

        return $mail
            ->action($this->customTemplate['button_label'] ?? 'Try Again', route('filament.admin.resources.students.view', ['record' => $this->studentName]))
            ->line('Thank you for your patience.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Timetable PDF Generation Failed',
            'message' => "Failed to generate timetable PDF for {$this->studentName}. Error: {$this->errorMessage}",
            'student_name' => $this->studentName,
            'error_message' => $this->errorMessage,
            'type' => 'error',
            'icon' => 'heroicon-o-exclamation-triangle',
            'created_at' => format_timestamp_now(),
        ];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): DatabaseMessage
    {
        return new DatabaseMessage([
            'title' => 'Timetable PDF Generation Failed',
            'message' => "Failed to generate timetable PDF for {$this->studentName}.",
            'student_name' => $this->studentName,
            'error_message' => $this->errorMessage,
            'type' => 'error',
            'icon' => 'heroicon-o-exclamation-triangle',
            'action_text' => 'Try Again',
            'action_url' => route('filament.admin.resources.students.index'),
        ]);
    }

    /**
     * Send Filament notification for real-time display
     */
    public function sendFilamentNotification(object $notifiable): void
    {
        FilamentNotification::make()
            ->title('Timetable PDF Generation Failed')
            ->body("Failed to generate timetable PDF for {$this->studentName}. Error: {$this->errorMessage}")
            ->danger()
            ->icon('heroicon-o-exclamation-triangle')
            ->duration(15000)
            ->actions([
                \Filament\Actions\Action::make('retry')
                    ->label('Try Again')
                    ->url(route('filament.admin.resources.students.index'))
                    ->color('primary'),
            ])
            ->sendToDatabase($notifiable);
    }
}
