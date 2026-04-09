<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class StudentBulkMessage extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $studentName,
        public string $subjectLine,
        public string $body,
        public string $senderName,
        public string $senderRole,
        public string $schoolName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.students.bulk-message',
            with: [
                'studentName' => $this->studentName,
                'subjectLine' => $this->subjectLine,
                'body' => $this->body,
                'senderName' => $this->senderName,
                'senderRole' => $this->senderRole,
                'schoolName' => $this->schoolName,
            ],
        );
    }
}
