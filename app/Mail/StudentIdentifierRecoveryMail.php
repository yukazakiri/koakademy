<?php

declare(strict_types=1);

namespace App\Mail;

use App\Settings\SiteSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class StudentIdentifierRecoveryMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public string $appName;

    public string $orgName;

    public string $signupUrl;

    /**
     * @param  list<array{student_name: string, student_id_label?: string, student_id: string|null, external_id_label?: string, external_id?: string|null, lrn?: string|null, student_type: string|null, program: string|null}>  $records
     */
    public function __construct(
        public string $recipientEmail,
        public array $records,
        public string $identifierHeading = 'Student ID',
    ) {
        $settings = app(SiteSettings::class);
        $this->appName = $settings->getAppName();
        $this->orgName = $settings->getOrganizationName();
        $this->signupUrl = url('/signup?email='.urlencode($recipientEmail));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->appName} — Your {$this->identifierHeading}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.student-identifier-recovery',
        );
    }
}
