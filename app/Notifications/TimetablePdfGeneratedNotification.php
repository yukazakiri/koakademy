<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Email\EmailTemplateRenderer;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TimetablePdfGeneratedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param  array<string, mixed>  $customTemplate
     */
    public function __construct(
        private string $filename,
        private string $studentName,
        private string $downloadUrl,
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
            'filename' => $this->filename,
            'download_url' => $this->downloadUrl,
        ];

        $mail = (new MailMessage)
            ->subject($renderer->subject('timetable_pdf_generated', $tokens, $this->customTemplate))
            ->greeting('Hello '.$notifiable->name.'!');

        foreach ($renderer->introLines('timetable_pdf_generated', $tokens, $this->customTemplate) as $line) {
            $mail->line($line);
        }

        return $mail
            ->action($this->customTemplate['button_label'] ?? 'Download PDF', $this->downloadUrl)
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Timetable PDF Generated',
            'message' => "The timetable PDF for {$this->studentName} is ready for download.",
            'filename' => $this->filename,
            'download_url' => $this->downloadUrl,
            'student_name' => $this->studentName,
            'type' => 'success',
            'icon' => 'heroicon-o-document-arrow-down',
            'created_at' => format_timestamp_now(),
        ];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): DatabaseMessage
    {
        return new DatabaseMessage([
            'title' => 'Timetable PDF Generated',
            'message' => "The timetable PDF for {$this->studentName} is ready for download.",
            'filename' => $this->filename,
            'download_url' => $this->downloadUrl,
            'student_name' => $this->studentName,
            'type' => 'success',
            'icon' => 'heroicon-o-document-arrow-down',
            'action_url' => $this->downloadUrl,
            'action_text' => 'Download PDF',
        ]);
    }

    /**
     * Send Filament notification for real-time display
     */
    public function sendFilamentNotification(object $notifiable): void
    {
        FilamentNotification::make()
            ->title('Timetable PDF Generated Successfully')
            ->body("The timetable PDF for {$this->studentName} is ready for download.")
            ->success()
            ->icon('heroicon-o-document-arrow-down')
            ->duration(10000)
            ->actions([
                \Filament\Actions\Action::make('download')
                    ->label('Download PDF')
                    ->url($this->downloadUrl)
                    ->openUrlInNewTab(),
            ])
            ->broadcast($notifiable)
            ->sendToDatabase($notifiable);
    }
}
