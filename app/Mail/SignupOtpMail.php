<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class SignupOtpMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public string $otp) {}

    public function envelope(): Envelope
    {
        $settings = app(\App\Settings\SiteSettings::class);
        $appName = $settings->getAppName();

        return new Envelope(
            subject: "{$appName} - Signup Verification Code",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.signup-otp',
        );
    }
}
