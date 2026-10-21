<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The built-in reasons a registrar can select when closing an application.
 *
 * Every reason carries the copy that is sent to the applicant, so a decision
 * never leaves the building with a vague "your application was rejected" email.
 * The copy is interpolated with the tokens built by
 * {@see \App\Services\ApplicantCancellationService}.
 */
enum ApplicantCancellationReason: string implements HasColor, HasLabel
{
    case IncompleteDocuments = 'incomplete_documents';
    case RequirementsNotMet = 'requirements_not_met';
    case ProgramAtCapacity = 'program_at_capacity';
    case NotEligible = 'not_eligible';
    case UnverifiableInformation = 'unverifiable_information';
    case DuplicateApplication = 'duplicate_application';
    case ApplicantWithdrew = 'applicant_withdrew';
    case DidNotCompleteProcess = 'did_not_complete_process';
    case Other = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::IncompleteDocuments => 'Incomplete or missing documents',
            self::RequirementsNotMet => 'Admission requirements not met',
            self::ProgramAtCapacity => 'Program is at full capacity',
            self::NotEligible => 'Not eligible for the program',
            self::UnverifiableInformation => 'Information could not be verified',
            self::DuplicateApplication => 'Duplicate application',
            self::ApplicantWithdrew => 'Applicant withdrew / lost interest',
            self::DidNotCompleteProcess => 'Did not complete the application process',
            self::Other => 'Other reason',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::IncompleteDocuments => 'Required documents were never submitted, or the uploads were unreadable.',
            self::RequirementsNotMet => 'The applicant does not meet the academic or residency requirements for the program.',
            self::ProgramAtCapacity => 'The program has no available slots for this term.',
            self::NotEligible => 'The applicant does not satisfy the entry requirements of the chosen program.',
            self::UnverifiableInformation => 'The submitted records could not be matched against a source we can verify.',
            self::DuplicateApplication => 'Another active application already exists for the same applicant and program.',
            self::ApplicantWithdrew => 'The applicant informed us that they are no longer pursuing this application.',
            self::DidNotCompleteProcess => 'The applicant stopped responding before the admission decision was made.',
            self::Other => 'Any other reason. A note is required so the record explains itself.',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::IncompleteDocuments, self::DidNotCompleteProcess => Color::Amber,
            self::RequirementsNotMet, self::NotEligible, self::UnverifiableInformation => Color::Red,
            self::ProgramAtCapacity => Color::Orange,
            self::DuplicateApplication => Color::Purple,
            self::ApplicantWithdrew => Color::Blue,
            self::Other => Color::Gray,
        };
    }

    /**
     * The subject line used for the notification email.
     */
    public function getSubject(): string
    {
        return match ($this) {
            self::IncompleteDocuments => 'Update on your application: documents still needed',
            self::RequirementsNotMet => 'Update on your application to {{ program }}',
            self::ProgramAtCapacity => 'Update on your application to {{ program }}',
            self::NotEligible => 'Update on your application to {{ program }}',
            self::UnverifiableInformation => 'Update on your application: we need to verify your records',
            self::DuplicateApplication => 'Update on your duplicate application',
            self::ApplicantWithdrew => 'Confirmation: your application has been closed',
            self::DidNotCompleteProcess => 'Update on your application to {{ program }}',
            self::Other => 'Update on your application to {{ program }}',
        };
    }

    /**
     * The paragraph that explains the decision to the applicant.
     *
     * `{{ reason_detail }}` is replaced by the registrar's note when one was given.
     */
    public function getBody(): string
    {
        return match ($this) {
            self::IncompleteDocuments => "We could not complete the review of your application because the documents required by the Registrar's Office were not submitted, or the files you uploaded could not be opened.\n\n{{ reason_detail }}",
            self::RequirementsNotMet => "After reviewing your application, we regret to inform you that you did not meet the academic and residency requirements for this program.\n\n{{ reason_detail }}",
            self::ProgramAtCapacity => "Your application was received in good order, but {{ program }} has reached its capacity for this term and we were unable to allocate a slot to you.\n\n{{ reason_detail }}",
            self::NotEligible => "After reviewing your application, we regret to inform you that you do not meet the entry requirements for {{ program }}.\n\n{{ reason_detail }}",
            self::UnverifiableInformation => "We were unable to verify the records submitted with your application against an official source, so we could not confirm your eligibility.\n\n{{ reason_detail }}",
            self::DuplicateApplication => "We found another active application for {{ program }} under your name, so we closed this one to keep your records accurate.\n\n{{ reason_detail }}",
            self::ApplicantWithdrew => 'As requested, we have closed your application for {{ program }}. No further action is needed from you, and your personal information remains on file in line with our retention policy.',
            self::DidNotCompleteProcess => "We were unable to finish reviewing your application because we did not hear back after our follow-up messages. We have therefore closed this application.\n\n{{ reason_detail }}",
            self::Other => "After reviewing your application, we are unable to proceed with it at this time.\n\n{{ reason_detail }}",
        };
    }

    /**
     * Guidance shown to the applicant so the email is actionable.
     */
    public function getNextSteps(): string
    {
        return match ($this) {
            self::IncompleteDocuments, self::UnverifiableInformation => 'You are welcome to submit a fresh application for the next intake with the complete set of documents. Reply to this email if you believe something was uploaded incorrectly.',
            self::RequirementsNotMet, self::NotEligible => 'You are welcome to apply to another program in a future intake, or to meet the entry requirements and apply again. The Registrar\'s Office can explain which requirements were not met.',
            self::ProgramAtCapacity => 'Please contact the Registrar\'s Office to be placed on the waiting list, and watch for the announcement of the next intake.',
            self::DuplicateApplication => 'If you applied more than once by mistake, reply to this email so we can reopen the correct application.',
            self::ApplicantWithdrew => 'If you change your mind, you are welcome to submit a new application for a future intake.',
            self::DidNotCompleteProcess => 'You are welcome to submit a fresh application for the next intake. Reply to this email if you would like your documents kept on file.',
            self::Other => 'If you would like to know more about this decision, or you wish to apply again in a future intake, please contact the Registrar\'s Office and quote your student reference.',
        };
    }

    /**
     * Whether the registrar must explain the decision in writing.
     */
    public function requiresNote(): bool
    {
        return $this === self::Other;
    }

    /**
     * Whether the applicant initiated the closure, which changes the tone of the email.
     */
    public function isApplicantInitiated(): bool
    {
        return $this === self::ApplicantWithdrew;
    }
}
