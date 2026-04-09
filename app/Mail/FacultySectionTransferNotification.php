<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email notification for faculty when students are transferred to their class sections
 */
final class FacultySectionTransferNotification extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public array $emailData,
        public bool $isBulkTransfer = false
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->isBulkTransfer
            ? 'Class Roster Update - Multiple Student Transfers'
            : 'Class Roster Update - Student Transfer Notification';

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $view = $this->isBulkTransfer
            ? 'emails.faculty-bulk-section-transfer'
            : 'emails.faculty-section-transfer';

        return new Content(
            view: $view,
            with: array_merge($this->emailData, ['is_bulk' => $this->isBulkTransfer])
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
