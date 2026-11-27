<?php

declare(strict_types=1);

namespace App\Mail;

use App\Settings\SiteSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class FacultyIdentifierRecoveryMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public string $appName;

    public string $orgName;

    public string $signupUrl;

    /**
     * @param  list<array{faculty_name: string, faculty_id_label?: string, faculty_id_number: string, department: string|null, school_name: string|null}>  $records
     */
    public function __construct(
        public string $recipientEmail,
        public array $records,
        public string $identifierHeading = 'Faculty ID',
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
            view: 'emails.auth.faculty-identifier-recovery',
        );
    }
}
