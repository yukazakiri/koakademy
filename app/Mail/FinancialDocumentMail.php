<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\FinancialDocumentType;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class FinancialDocumentMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $document
     */
    public function __construct(
        public readonly FinancialDocumentType $type,
        public readonly array $document,
        private readonly string $pdfBytes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->type->label().' #'.$this->document['document']['number'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.finance.financial-document',
            text: 'emails.finance.financial-document-text',
            with: ['financialDocument' => $this->document, 'documentType' => $this->type],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn (): string => $this->pdfBytes,
                sprintf(
                    'official-e%s-%s.pdf',
                    $this->type === FinancialDocumentType::Receipt ? 'receipt' : 'invoice',
                    $this->document['document']['number'],
                ),
            )->withMime('application/pdf'),
        ];
    }
}
