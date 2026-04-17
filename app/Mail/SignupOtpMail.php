<?php

declare(strict_types=1);

namespace App\Mail;

use App\Settings\SiteSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class SignupOtpMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public string $appName;

    public string $orgName;

    public function __construct(public string $otp)
    {
        $settings = app(SiteSettings::class);
        $this->appName = $settings->getAppName();
        $this->orgName = $settings->getOrganizationName();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->appName} — Verify Your Email",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.signup-otp',
        );
    }
}
