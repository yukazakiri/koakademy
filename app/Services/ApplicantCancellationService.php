<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApplicantCancellationReason;
use App\Enums\StudentStatus;
use App\Models\ApplicantCancellation;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ApplicantApplicationCancelled;
use App\Settings\SiteSettings;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Closes an application and tells the applicant why.
 *
 * The decision is recorded before the email is queued, so a failure to deliver
 * never loses the reason the registrar selected. The student record moves to a
 * terminal status, which takes them out of the working admissions queue, and
 * the decision can be reversed later without losing the original record.
 */
final class ApplicantCancellationService
{
    /**
     * The built-in reasons offered in the cancellation dialog.
     *
     * @return list<array{value: string, label: string, description: string, requires_note: bool, applicant_initiated: bool}>
     */
    public function reasons(): array
    {
        return collect(ApplicantCancellationReason::cases())
            ->map(fn (ApplicantCancellationReason $reason): array => [
                'value' => $reason->value,
                'label' => (string) $reason->getLabel(),
                'description' => (string) $reason->getDescription(),
                'requires_note' => $reason->requiresNote(),
                'applicant_initiated' => $reason->isApplicantInitiated(),
            ])
            ->values()
            ->all();
    }

    /**
     * Build the exact email that would be sent, without sending anything.
     *
     * The registrar sees this in the dialog before confirming, so the message
     * that leaves the office is never a surprise.
     *
     * @return array{subject: string, body: string, salutation: string, reason_label: string, reason_description: string, requires_note: bool}
     */
    public function preview(Student $student, ApplicantCancellationReason $reason, ?string $note = null): array
    {
        $message = $this->compose($student, $reason, $note);

        return [
            ...$message,
            'reason_label' => (string) $reason->getLabel(),
            'reason_description' => (string) $reason->getDescription(),
            'requires_note' => $reason->requiresNote(),
        ];
    }

    /**
     * Close an application and notify the applicant.
     */
    public function cancel(
        Student $student,
        User $actor,
        ApplicantCancellationReason $reason,
        ?string $note = null,
        bool $sendEmail = true,
    ): ApplicantCancellation {
        $note = $note !== null ? mb_trim($note) : null;

        if ($reason->requiresNote() && ($note === null || $note === '')) {
            throw new RuntimeException('A note is required when cancelling for this reason.');
        }

        if ($student->status !== StudentStatus::Applicant) {
            throw new RuntimeException('Only applications that are still open can be cancelled.');
        }

        $message = $this->compose($student, $reason, $note);

        $cancellation = DB::transaction(function () use ($student, $actor, $reason, $note, $message): ApplicantCancellation {
            $cancellation = ApplicantCancellation::query()->create([
                'student_id' => $student->id,
                'reason_code' => $reason->value,
                'reason_label' => (string) $reason->getLabel(),
                'note' => $note,
                'email_subject' => $message['subject'],
                'email_body' => $message['salutation']."\n\n".$message['body'],
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
            ]);

            // The terminal status takes the application out of the working queue.
            $student->forceFill(['status' => StudentStatus::Rejected->value])->save();

            return $cancellation;
        });

        if ($sendEmail && filled($student->email)) {
            $student->notify(new ApplicantApplicationCancelled(
                reason: $reason,
                reasonLabel: (string) $reason->getLabel(),
                note: $note,
                subject: $message['subject'],
                body: $message['body'],
                senderName: $actor->name,
                senderRole: $actor->role?->getLabel() ?? 'Administrator',
            ));

            $cancellation->forceFill(['email_sent_at' => now()])->save();
        }

        return $cancellation;
    }

    /**
     * Reverse a cancellation and put the application back in the queue.
     */
    public function revert(ApplicantCancellation $cancellation, User $actor, ?string $reason = null): ApplicantCancellation
    {
        if ($cancellation->isReverted()) {
            throw new RuntimeException('This cancellation was already reversed.');
        }

        $student = $cancellation->student;

        if (! $student instanceof Student) {
            throw new RuntimeException('The applicant record for this cancellation no longer exists.');
        }

        return DB::transaction(function () use ($cancellation, $student, $actor, $reason): ApplicantCancellation {
            $cancellation->forceFill([
                'reverted_by' => $actor->id,
                'reverted_at' => now(),
                'revert_reason' => $reason !== null ? mb_trim($reason) : null,
            ])->save();

            $student->forceFill(['status' => StudentStatus::Applicant->value])->save();

            return $cancellation;
        });
    }

    /**
     * The most recent cancellation that has not been reversed.
     */
    public function activeFor(Student $student): ?ApplicantCancellation
    {
        return ApplicantCancellation::query()
            ->where('student_id', $student->id)
            ->active()
            ->orderByDesc('cancelled_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Compose the subject and body that are sent to the applicant.
     *
     * The salutation is returned separately because the email template renders
     * it, exactly like every other enrollment email in the installation.
     *
     * @return array{subject: string, body: string, salutation: string}
     */
    private function compose(Student $student, ApplicantCancellationReason $reason, ?string $note): array
    {
        $student->loadMissing('Course.department');

        $program = $student->Course?->code ?? 'the program you applied for';
        $department = $student->Course?->department?->code;
        $applicantName = mb_trim((string) ($student->first_name.' '.$student->last_name));
        $noteText = filled($note)
            ? mb_trim((string) $note)
            : 'No further explanation was recorded for this decision.';

        $tokens = [
            'applicant_name' => $applicantName,
            'program' => $program,
            'department' => (string) ($department ?? ''),
            'reason_detail' => $noteText,
            'next_steps' => $reason->getNextSteps(),
            'organization_name' => (string) (app(SiteSettings::class)->getBrandingArray()['organizationName'] ?? config('app.name')),
        ];

        $subject = $this->interpolate($reason->getSubject(), $tokens);
        $body = $this->interpolate($reason->getBody(), $tokens);

        $paragraphs = [
            $body,
            '',
            '### What you can do next',
            '',
            $tokens['next_steps'],
        ];

        return [
            'subject' => $subject,
            'body' => implode("\n", $paragraphs),
            'salutation' => 'Dear '.$applicantName.',',
        ];
    }

    /**
     * @param  array<string, string>  $tokens
     */
    private function interpolate(string $template, array $tokens): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/i',
            fn (array $matches): string => $tokens[$matches[1]] ?? $matches[0],
            $template,
        );
    }
}
