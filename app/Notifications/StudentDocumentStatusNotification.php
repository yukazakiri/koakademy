<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class StudentDocumentStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $documentLabels
     */
    public function __construct(
        public readonly Student $student,
        public readonly string $eventType,
        public readonly array $documentLabels,
        public readonly ?string $reason = null,
        public readonly ?string $customMessage = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $channels = [];

        if (method_exists($notifiable, 'routeNotificationFor')) {
            if ($notifiable instanceof \App\Models\User || $notifiable instanceof Student) {
                $channels[] = 'database';
            }
            if (filled($notifiable->email ?? null)) {
                $channels[] = 'mail';
            }
        }

        return $channels === [] ? ['database'] : $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $docList = implode(', ', $this->documentLabels);
        $portalUrl = url('/student/documents');

        if ($this->eventType === 'rejected') {
            $mail = (new MailMessage)
                ->subject("Action Required: Re-upload {$docList}")
                ->greeting("Hello {$this->student->first_name},")
                ->line("Your submitted document ({$docList}) requires re-submission.");

            if (filled($this->reason)) {
                $mail->line("Registrar Note: {$this->reason}");
            }

            return $mail
                ->action('Upload Updated Document', $portalUrl)
                ->line('Please upload a clear copy through your Student Document Vault at your earliest convenience.');
        }

        $mail = (new MailMessage)
            ->subject('Reminder: Pending Required Student Documents')
            ->greeting("Hello {$this->student->first_name},")
            ->line("Our registrar's office noted the following required documents are still pending on your record: {$docList}.");

        if (filled($this->customMessage)) {
            $mail->line($this->customMessage);
        }

        return $mail
            ->action('Open Document Vault', $portalUrl)
            ->line('You may upload clear scans directly online or submit physical copies to the registrar.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $docList = implode(', ', $this->documentLabels);

        return [
            'type' => 'student_document_'.$this->eventType,
            'student_id' => $this->student->id,
            'student_number' => $this->student->student_id,
            'title' => $this->eventType === 'rejected'
                ? "Document requires re-upload: {$docList}"
                : 'Missing required documents reminder',
            'body' => $this->eventType === 'rejected'
                ? ($this->reason ?: "Please upload a clearer copy of {$docList}.")
                : ($this->customMessage ?: "Pending documents: {$docList}."),
            'documents' => $this->documentLabels,
            'action_url' => '/student/documents',
        ];
    }
}
