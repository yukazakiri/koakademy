<?php

declare(strict_types=1);

namespace App\Email;

/**
 * The single source of truth for every template-driven email in Koakademy.
 *
 * Each entry owns the built-in default subject and intro copy plus the tokens that
 * may be interpolated. Templates only ever replace the subject line and the prose
 * around the structured body — subject tables, fee breakdowns, schedule rows and
 * attachments are always rendered from code, so a template can never drop data that
 * a student is legally entitled to see.
 *
 * Authentication email (two-factor codes, password resets, magic login links and
 * signup OTPs) is deliberately absent: those messages carry credentials, and letting
 * a template author rewrite them would turn template editing into an account-takeover
 * primitive. They stay hardcoded in their notification classes.
 */
final class EmailTemplateCatalog
{
    /**
     * Enrollment emails are configured per workflow step on the policy draft.
     *
     * @var list<string>
     */
    public const ENROLLMENT_KEYS = ['status_update', 'academic_verified', 'assessment', 'custom'];

    /**
     * Non-enrollment emails are configured once for the whole installation.
     *
     * Deliberately limited to email that is actually sent. Database-only
     * notifications (StudentTuitionUpdateRequestReviewedNotification,
     * AdminFacultyNoticeNotification, PdfGenerationCompleted) and
     * EventReminderNotification, which never uses the mail channel, are excluded
     * because there is no mail message to template. EnrollmentVerified is excluded
     * too because it has no dispatch site and therefore never fires.
     *
     * @var list<string>
     */
    public const SYSTEM_KEYS = [
        'applicant_approved_for_requirements',
        'class_schedule_changed',
        'student_tuition_adjusted',
        'statement_of_account_adjusted',
        'timetable_pdf_generated',
        'timetable_pdf_failed',
        'financial_document',
        'transaction_receipt',
        'class_change',
        'faculty_section_transfer',
        'student_section_transfer',
    ];

    /**
     * @return array<string, array{subject: string, intro: string, tokens: array<string, string>, protected: bool}>
     */
    public static function all(): array
    {
        return array_merge(self::enrollment(), self::system());
    }

    /**
     * Every template key that can be configured.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * @return array{subject: string, intro: string, tokens: array<string, string>, protected: bool}|null
     */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /**
     * @return array{subject: string, intro: string, tokens: array<string, string>, protected: bool}|null
     */
    public static function defaultFor(string $key): ?array
    {
        return self::find($key);
    }

    /**
     * Authentication email that must never become template-editable.
     *
     * @return array<string, string>
     */
    public static function excludedAuthenticationEmail(): array
    {
        return [
            'two_factor_code' => 'Two-Factor Authentication Code',
            'password_reset' => 'Password Reset',
            'email_authentication_code' => 'Email Authentication Code',
            'magic_login_link' => 'Passwordless sign-in link',
            'signup_otp' => 'Signup email verification',
        ];
    }

    /**
     * @return array<string, array{subject: string, intro: string, tokens: array<string, string>, protected: bool}>
     */
    private static function enrollment(): array
    {
        return [
            'status_update' => [
                'subject' => 'Enrollment Status Update: {{ status }} | {{ organization_name }}',
                'intro' => "Hello {{ student_name }},\n\nYour enrollment application has advanced to the following milestone in our admissions pipeline. Please review your updated status and next steps below.",
                'tokens' => self::enrollmentTokens(),
                'protected' => false,
            ],
            'custom' => [
                'subject' => 'Important Update Regarding Your Application | {{ organization_name }}',
                'intro' => "Dear {{ student_name }},\n\nWe have an important announcement regarding your admission record for {{ program }}.\n\nPlease check the instructions below and log in to your account.",
                'tokens' => self::enrollmentTokens(),
                'protected' => false,
            ],
            'academic_verified' => [
                'subject' => 'Academic Clearance Approved - Subjects & Fees | {{ organization_name }}',
                'intro' => "Dear {{ student_name }},\n\nWe are pleased to inform you that your subject enrollment and curriculum evaluation have been officially verified and approved by the Academic Department.\n\nPlease proceed to settle your downpayment to finalize your matriculation.",
                'tokens' => self::enrollmentTokens(),
                'protected' => false,
            ],
            'assessment' => [
                'subject' => 'Enrollment Confirmed & Assessment Certificate | {{ organization_name }}',
                'intro' => "Congratulations {{ student_name }}!\n\nYour enrollment has been successfully completed and approved. You are now officially enrolled for {{ school_year }} · Semester {{ semester }}.\n\nYour official Certificate of Matriculation is attached as a PDF to this email.",
                'tokens' => self::enrollmentTokens(),
                'protected' => false,
            ],
        ];
    }

    /** @return array<string, string> */
    private static function enrollmentTokens(): array
    {
        return [
            'student_name' => 'Maria Santos',
            'status' => 'Interview Scheduled',
            'step_name' => 'Interview Stage',
            'school_year' => '2026-2027',
            'semester' => '1',
            'program' => 'BSCS',
            'portal_url' => '/student',
            'organization_name' => 'KoAkademy',
        ];
    }

    /**
     * @return array<string, array{subject: string, intro: string, tokens: array<string, string>, protected: bool}>
     */
    private static function system(): array
    {
        return [
            'applicant_approved_for_requirements' => [
                'subject' => 'Online Application Approved - Visit the Registrar | {{ organization_name }}',
                'intro' => 'Good news — your application for {{ program }} has been reviewed and approved for the next step of the admission process.',
                'tokens' => ['applicant_name' => 'Maria Santos', 'program' => 'BSCS', 'department' => 'CS', 'organization_name' => 'KoAkademy', 'sender_name' => 'Registrar Office'],
                'protected' => false,
            ],
            'class_schedule_changed' => [
                'subject' => 'Schedule Update: {{ class_title }}',
                'intro' => "Hello {{ student_name }},\n\nYour class schedule has been updated.\n\nPlease review the update and adjust your plans accordingly. If you have any questions or conflicts with the new schedule, contact your instructor or the registrar as soon as possible.",
                'tokens' => ['student_name' => 'Maria Santos', 'class_title' => 'BSCS 101', 'changed_by' => 'Registrar', 'portal_url' => '/student'],
                'protected' => false,
            ],
            'student_tuition_adjusted' => [
                'subject' => 'Your student tuition was adjusted',
                'intro' => "Hello {{ student_name }},\n\nYour tuition assessment for {{ school_year }}, Semester {{ semester }} was updated.\n\nIf anything looks incorrect, please contact the Finance Office.",
                'tokens' => ['student_name' => 'Maria Santos', 'school_year' => '2026-2027', 'semester' => '1', 'reason' => 'Scholarship applied', 'actor_name' => 'Registrar'],
                'protected' => false,
            ],
            'statement_of_account_adjusted' => [
                'subject' => 'Your Statement of Account has been adjusted',
                'intro' => "Hello {{ student_name }},\n\nThe registrar has adjusted your Statement of Account.\n\n{{ adjustment_note }}\n\nIf you believe this adjustment was made in error, please contact the registrar or accounting office as soon as possible.",
                'tokens' => ['student_name' => 'Maria Santos', 'adjustment_note' => 'Lab fee corrected.', 'changed_by' => 'Registrar'],
                'protected' => false,
            ],
            'timetable_pdf_generated' => [
                'subject' => 'Timetable PDF Generated Successfully',
                'intro' => "The timetable PDF for {{ student_name }} has been generated successfully.\n\nYou can download the PDF using the link below. This link will be available for 24 hours.",
                'tokens' => ['student_name' => 'Maria Santos', 'download_url' => '/student/timetable'],
                'protected' => false,
            ],
            'timetable_pdf_failed' => [
                'subject' => 'Timetable PDF Generation Failed',
                'intro' => "There was an error generating the timetable PDF for {{ student_name }}.\n\nError details: {{ error_message }}\n\nPlease try again or contact support if the problem persists.",
                'tokens' => ['student_name' => 'Maria Santos', 'error_message' => 'Template not found'],
                'protected' => false,
            ],
            'financial_document' => [
                'subject' => 'Your {{ document_type }} is available',
                'intro' => "Hello {{ recipient_name }},\n\nYour **{{ document_type }}** ({{ document_reference }}) has been issued and is ready to download.",
                'tokens' => ['recipient_name' => 'Maria Santos', 'document_type' => 'Statement of Account', 'document_reference' => 'SOA-2026-0001', 'amount' => '0.00', 'download_url' => '/student/documents'],
                'protected' => false,
            ],
            'transaction_receipt' => [
                'subject' => 'Receipt for your payment | {{ organization_name }}',
                'intro' => "Hello {{ payer_name }},\n\nThank you for your payment of **{{ amount }}**.\n\nYour official receipt is attached to this email.",
                'tokens' => ['payer_name' => 'Maria Santos', 'amount' => '15,000.00', 'receipt_number' => 'RCPT-2026-0001', 'payment_method' => 'Cash', 'organization_name' => 'KoAkademy'],
                'protected' => false,
            ],
            'class_change' => [
                'subject' => 'Class change for {{ class_title }}',
                'intro' => "Hello {{ student_name }},\n\nA class you are enrolled in has changed.\n\n{{ context }}\n\nPlease check the updated details below.",
                'tokens' => ['student_name' => 'Maria Santos', 'class_title' => 'BSCS 101', 'context' => 'The room assignment changed.', 'portal_url' => '/student'],
                'protected' => false,
            ],
            'faculty_section_transfer' => [
                'subject' => 'Section transfer approved | {{ section_name }}',
                'intro' => "Hello {{ faculty_name }},\n\nA request to transfer **{{ section_name }}** has been approved.\n\n{{ context }}",
                'tokens' => ['faculty_name' => 'Dr. Rivera', 'section_name' => 'BSCS 302', 'context' => 'Effective next term.', 'portal_url' => '/faculty'],
                'protected' => false,
            ],
            'student_section_transfer' => [
                'subject' => 'Section transfer approved | {{ section_name }}',
                'intro' => "Hello {{ student_name }},\n\nYour request to transfer **{{ section_name }}** has been approved.\n\n{{ context }}",
                'tokens' => ['student_name' => 'Maria Santos', 'section_name' => 'BSCS 302', 'context' => 'Effective next term.', 'portal_url' => '/student'],
                'protected' => false,
            ],
        ];
    }
}
