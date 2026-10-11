<?php

declare(strict_types=1);

namespace App\Email;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Pure content construction, shared by delivery and the synthetic editor preview.
 * Account lookup, PDF generation and attachment loading belong to the sender.
 */
final readonly class EnrollmentAssessmentMailMessage
{
    public function __construct(private EmailTemplateRenderer $renderer) {}

    /**
     * @param  array<string, string>  $tokens
     * @param  array<string, mixed>  $configuration
     * @param  array<string, mixed>  $siteSettings
     * @param  array{has_account: bool, student_id_display: ?string, student_email: ?string, signup_url: ?string}  $portalAccount
     */
    public function build(
        array $tokens,
        array $configuration,
        array $siteSettings,
        ?string $logoUrl,
        array $portalAccount,
        bool $pdfAttached = false,
    ): MailMessage {
        return (new MailMessage)
            ->subject($this->renderer->subject('assessment', $tokens, $configuration))
            ->markdown('emails.enrollment.official_enrollment', [
                'student_name' => $tokens['student_name'],
                'intro' => $this->renderer->intro('assessment', $tokens, $configuration),
                'school_year' => mb_convert_encoding($tokens['school_year'], 'UTF-8', 'auto'),
                'semester' => mb_convert_encoding($tokens['semester'], 'UTF-8', 'auto'),
                'siteSettings' => $siteSettings,
                'logoUrl' => $logoUrl,
                'has_portal_account' => $portalAccount['has_account'],
                'student_id_display' => $portalAccount['student_id_display'],
                'student_email' => $portalAccount['student_email'],
                'signup_url' => $portalAccount['signup_url'],
                'pdfAttached' => $pdfAttached,
            ]);
    }
}
