<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FinancialDeliveryStatus;
use App\Enums\FinancialDocumentStatus;
use App\Enums\FinancialDocumentType;
use App\Jobs\SendFinancialDocumentJob;
use App\Models\FinancialDocumentDelivery;
use App\Models\FinancialDocumentIssuance;
use App\Models\StudentEnrollment;
use App\Models\StudentTransaction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final readonly class FinancialDocumentService
{
    public function __construct(
        private FinanceDocumentSettingsService $settings,
        private TransactionReceiptDataService $receiptData,
        private EnrollmentBillingService $billing,
        private GeneralSettingsService $generalSettings,
    ) {}

    public function issueReceipt(StudentTransaction $studentTransaction, bool $autoDeliver = true): ?FinancialDocumentIssuance
    {
        $studentTransaction->loadMissing(['student.Course', 'transaction.user']);
        $transaction = $studentTransaction->transaction;

        if (
            ! $transaction instanceof Transaction
            || ! in_array(mb_strtolower((string) $studentTransaction->status), ['paid', 'completed'], true)
            || ! in_array(mb_strtolower((string) $transaction->status), ['paid', 'completed'], true)
            || (float) $studentTransaction->amount <= 0
        ) {
            return null;
        }

        $configuration = $this->settings->get();
        $existing = FinancialDocumentIssuance::query()
            ->where('type', FinancialDocumentType::Receipt->value)
            ->where('transaction_id', $transaction->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        if ($autoDeliver && ! $configuration['automatic_receipts_enabled']) {
            return null;
        }

        $student = $studentTransaction->student;
        $recipient = $this->validEmail($student?->email);
        $paperReference = filled($transaction->invoicenumber) ? (string) $transaction->invoicenumber : null;
        $status = FinancialDocumentStatus::Ready;

        if ($configuration['require_paper_or_reference'] && $paperReference === null) {
            $status = FinancialDocumentStatus::AwaitingReference;
        } elseif ($recipient === null) {
            $status = FinancialDocumentStatus::Skipped;
        }

        $snapshot = $this->receiptData->build($transaction, $studentTransaction);
        unset($snapshot['email_delivery']);

        $issuance = $this->createIssuance(
            type: FinancialDocumentType::Receipt,
            snapshot: $snapshot,
            status: $status,
            studentId: $student?->id,
            transactionId: $transaction->id,
            enrollmentId: $studentTransaction->student_enrollment_id,
            tuitionId: null,
            issuerId: $transaction->user_id,
            recipient: $recipient,
            paperReference: $paperReference,
        );

        $transaction->update([
            'receipt_email_status' => match ($status) {
                FinancialDocumentStatus::AwaitingReference => 'awaiting_reference',
                FinancialDocumentStatus::Skipped => 'skipped',
                default => 'ready',
            },
            'receipt_email_recipient' => $recipient,
            'receipt_email_error' => $status === FinancialDocumentStatus::AwaitingReference
                ? 'A paper O.R. reference is required before delivery.'
                : null,
        ]);

        if (
            $status === FinancialDocumentStatus::Ready
            && $autoDeliver
            && $configuration['automatic_receipts_enabled']
            && $configuration['mail_delivery_available']
            && $recipient !== null
        ) {
            return $this->queueDelivery($issuance, $recipient);
        }

        return $issuance;
    }

    public function issueInvoice(StudentEnrollment $enrollment, User $issuer, string $recipient): FinancialDocumentIssuance
    {
        $configuration = $this->settings->get();

        if (! $configuration['manual_invoices_enabled']) {
            throw ValidationException::withMessages(['invoice' => 'Manual eInvoice delivery is disabled in Finance Documents settings.']);
        }

        if (! $configuration['mail_delivery_available']) {
            throw ValidationException::withMessages(['recipient' => 'Email delivery is unavailable. Enable the mail channel and configure a sender address first.']);
        }

        $recipient = $this->requireValidEmail($recipient);
        $enrollment->loadMissing([
            'student.Course',
            'studentTuition.enrollment',
            'studentTuition.enrollmentDiscount',
            'additionalFees',
            'enrollmentTransactions.transaction',
        ]);

        $tuition = $enrollment->studentTuition;
        if (! $tuition) {
            throw ValidationException::withMessages(['invoice' => 'This enrollment does not have a tuition assessment.']);
        }

        $additionalFees = $enrollment->additionalFees
            ->sum(fn ($fee): float => (float) $fee->amount);
        $billing = $this->billing->toSummaryArray($tuition, $additionalFees);

        if ((float) $billing['balance_due'] <= 0) {
            throw ValidationException::withMessages(['invoice' => 'An eInvoice can only be issued for an enrollment with an outstanding balance.']);
        }

        $settings = $this->generalSettings->getGlobalSettingsModel();
        $charges = collect([
            ['label' => 'Lecture tuition', 'amount' => (float) $tuition->total_lectures],
            ['label' => 'Laboratory fees', 'amount' => (float) $tuition->total_laboratory],
            [
                'label' => 'Other tuition components',
                'amount' => max(
                    0.0,
                    (float) $tuition->total_tuition
                        - (float) $tuition->total_lectures
                        - (float) $tuition->total_laboratory,
                ),
            ],
            ['label' => 'Miscellaneous fees', 'amount' => (float) $tuition->total_miscelaneous_fees],
            ...$enrollment->additionalFees->map(fn ($fee): array => [
                'label' => (string) $fee->fee_name,
                'amount' => (float) $fee->amount,
            ])->all(),
        ])->filter(fn (array $charge): bool => abs((float) $charge['amount']) > 0.00001);
        $assessmentAdjustment = (float) $billing['overall_tuition'] - (float) $charges->sum('amount');
        if (abs($assessmentAdjustment) > 0.00001) {
            $charges->push([
                'label' => 'Assessment adjustment',
                'amount' => $assessmentAdjustment,
            ]);
        }
        $snapshot = [
            'student' => [
                'name' => $enrollment->student?->full_name ?? 'N/A',
                'student_id' => $enrollment->student?->student_id ?? 'N/A',
                'email' => $recipient,
                'course' => $enrollment->student?->Course?->code ?? 'N/A',
                'year_level' => $enrollment->academic_year,
            ],
            'billing_period' => [
                'school_year' => $enrollment->school_year,
                'semester' => $enrollment->semester,
            ],
            'charges' => $charges->values()->all(),
            'discount' => [
                'name' => $tuition->enrollmentDiscount?->name,
                'percentage' => (int) $tuition->discount,
            ],
            'totals' => [
                'assessed' => (float) $billing['overall_tuition'],
                'paid' => (float) $billing['total_paid'],
                'balance' => (float) $billing['balance_due'],
            ],
            'payments' => $enrollment->enrollmentTransactions
                ->filter(fn (StudentTransaction $link): bool => in_array(mb_strtolower((string) $link->status), ['paid', 'completed'], true))
                ->map(fn (StudentTransaction $link): array => [
                    'date' => $link->transaction?->transaction_date?->format('F d, Y'),
                    'reference' => $link->transaction?->invoicenumber,
                    'amount' => (float) $link->amount,
                ])
                ->values()
                ->all(),
            'currency' => $this->generalSettings->getCurrency(),
            'institution' => [
                'name' => $settings?->school_portal_title ?: config('app.name'),
                'description' => $settings?->school_portal_description,
                'support_email' => $settings?->support_email,
                'support_phone' => $settings?->support_phone,
            ],
        ];

        $issuance = $this->createIssuance(
            type: FinancialDocumentType::Invoice,
            snapshot: $snapshot,
            status: FinancialDocumentStatus::Ready,
            studentId: $enrollment->student?->id,
            transactionId: null,
            enrollmentId: $enrollment->id,
            tuitionId: $tuition->id,
            issuerId: $issuer->id,
            recipient: $recipient,
            paperReference: null,
        );

        return $this->queueDelivery($issuance, $recipient);
    }

    public function queueDelivery(
        FinancialDocumentIssuance $issuance,
        string $recipient,
        ?string $paperReference = null,
    ): FinancialDocumentIssuance {
        $recipient = $this->requireValidEmail($recipient);
        $configuration = $this->settings->get();

        if (! $configuration['mail_delivery_available']) {
            throw ValidationException::withMessages(['recipient' => 'Email delivery is unavailable. Enable the mail channel and configure a sender address first.']);
        }

        $delivery = DB::transaction(function () use ($issuance, $recipient, $paperReference, $configuration): FinancialDocumentDelivery {
            $locked = FinancialDocumentIssuance::query()->lockForUpdate()->findOrFail($issuance->id);

            if ($locked->status === FinancialDocumentStatus::Queued) {
                throw ValidationException::withMessages(['recipient' => 'A delivery is already queued for this document.']);
            }

            if ($locked->status === FinancialDocumentStatus::Revoked) {
                throw ValidationException::withMessages(['recipient' => 'A revoked document cannot be delivered.']);
            }

            if ($locked->type === FinancialDocumentType::Receipt) {
                $paperReference = filled($paperReference) ? mb_trim((string) $paperReference) : $locked->paper_reference;
                if ($configuration['require_paper_or_reference'] && blank($paperReference)) {
                    throw ValidationException::withMessages(['reference_number' => 'Enter the paper O.R. number before sending the official eReceipt.']);
                }

                if (filled($paperReference) && $locked->paper_reference !== $paperReference) {
                    $snapshot = $locked->snapshot;
                    $snapshot['reference_number'] = $paperReference;
                    $staleDisk = $locked->disk;
                    $stalePath = $locked->pdf_path;
                    $locked->paper_reference = $paperReference;
                    $locked->snapshot = $snapshot;
                    $locked->integrity_signature = $this->sign($snapshot);
                    $locked->disk = null;
                    $locked->pdf_path = null;
                    $locked->pdf_checksum = null;
                    $locked->transaction?->update(['invoicenumber' => $paperReference]);

                    if ($staleDisk !== null && $stalePath !== null) {
                        DB::afterCommit(static function () use ($staleDisk, $stalePath): void {
                            Storage::disk($staleDisk)->delete($stalePath);
                        });
                    }
                }
            }

            $delivery = $locked->deliveries()->create([
                'uuid' => (string) Str::uuid(),
                'recipient' => $recipient,
                'status' => FinancialDeliveryStatus::Queued,
                'queued_at' => now(),
            ]);

            $locked->forceFill([
                'recipient' => $recipient,
                'status' => FinancialDocumentStatus::Queued,
                'queued_at' => now(),
                'failed_at' => null,
                'failure_message' => null,
            ])->save();

            if ($locked->transaction_id) {
                $locked->transaction?->update([
                    'receipt_email_status' => 'pending',
                    'receipt_email_delivery_id' => $delivery->uuid,
                    'receipt_email_recipient' => $recipient,
                    'receipt_emailed_at' => null,
                    'receipt_email_failed_at' => null,
                    'receipt_email_error' => null,
                ]);
            }

            return $delivery;
        });

        try {
            SendFinancialDocumentJob::dispatch($delivery->id)->afterCommit();
        } catch (Throwable $throwable) {
            $this->markQueueFailure($delivery);
            report($throwable);

            throw new RuntimeException('The document email could not be queued. Please try again.', previous: $throwable);
        }

        return $issuance->fresh();
    }

    /**
     * @param  iterable<int, int|string>  $transactionIds
     */
    public function revokeForTransactions(iterable $transactionIds, string $reason): int
    {
        $ids = collect($transactionIds)
            ->filter(fn (mixed $id): bool => is_numeric($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return 0;
        }

        $issuances = FinancialDocumentIssuance::query()
            ->where('type', FinancialDocumentType::Receipt->value)
            ->whereIn('transaction_id', $ids)
            ->where('status', '!=', FinancialDocumentStatus::Revoked->value)
            ->lockForUpdate()
            ->get();
        $now = now();

        foreach ($issuances as $issuance) {
            $issuance->deliveries()
                ->whereIn('status', [
                    FinancialDeliveryStatus::Queued->value,
                    FinancialDeliveryStatus::Processing->value,
                ])
                ->update([
                    'status' => FinancialDeliveryStatus::Cancelled->value,
                    'error' => 'Delivery cancelled because the related payment was reversed.',
                ]);
            $issuance->forceFill([
                'status' => FinancialDocumentStatus::Revoked,
                'revoked_at' => $now,
                'revocation_reason' => $reason,
            ])->save();
            $issuance->transaction?->update([
                'receipt_email_status' => 'revoked',
                'receipt_email_error' => 'The related payment was reversed.',
            ]);
        }

        return $issuances->count();
    }

    public function hasValidIntegrity(FinancialDocumentIssuance $issuance): bool
    {
        $payload = $this->signaturePayload($issuance->snapshot);
        $keys = collect([
            config('app.key'),
            ...((array) config('app.previous_keys', [])),
        ])
            ->filter(fn (mixed $key): bool => is_string($key) && $key !== '')
            ->unique();

        return $keys->contains(
            fn (string $key): bool => hash_equals(
                $issuance->integrity_signature,
                hash_hmac('sha256', $payload, $key),
            ),
        );
    }

    /** @param array<string, mixed> $snapshot */
    public function sign(array $snapshot): string
    {
        return hash_hmac(
            'sha256',
            $this->signaturePayload($snapshot),
            (string) config('app.key'),
        );
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function createIssuance(
        FinancialDocumentType $type,
        array $snapshot,
        FinancialDocumentStatus $status,
        ?int $studentId,
        ?int $transactionId,
        ?int $enrollmentId,
        ?int $tuitionId,
        ?int $issuerId,
        ?string $recipient,
        ?string $paperReference,
    ): FinancialDocumentIssuance {
        $uuid = (string) Str::uuid();
        $verificationToken = Str::random(48);
        $issuedAt = now();
        $documentNumber = sprintf(
            '%s-%s-%s',
            $type->numberPrefix(),
            $issuedAt->format('Ymd'),
            mb_strtoupper(mb_substr(str_replace('-', '', $uuid), 0, 10)),
        );
        $snapshot['issuance'] = [
            'type' => $type->value,
            'document_number' => $documentNumber,
            'issued_at' => $issuedAt->toIso8601String(),
        ];

        return FinancialDocumentIssuance::query()->create([
            'uuid' => $uuid,
            'type' => $type,
            'student_id' => $studentId,
            'transaction_id' => $transactionId,
            'enrollment_id' => $enrollmentId,
            'tuition_id' => $tuitionId,
            'issued_by' => $issuerId,
            'document_number' => $documentNumber,
            'paper_reference' => $paperReference,
            'recipient' => $recipient,
            'status' => $status,
            'snapshot' => $snapshot,
            'integrity_signature' => $this->sign($snapshot),
            'verification_token' => $verificationToken,
            'verification_token_hash' => hash('sha256', $verificationToken),
            'issued_at' => $issuedAt,
        ]);
    }

    private function markQueueFailure(FinancialDocumentDelivery $delivery): void
    {
        $delivery->update([
            'status' => FinancialDeliveryStatus::Failed,
            'failed_at' => now(),
            'error' => 'The document email could not be queued.',
        ]);
        $delivery->issuance()->update([
            'status' => FinancialDocumentStatus::Failed,
            'failed_at' => now(),
            'failure_message' => 'The document email could not be queued.',
        ]);
    }

    private function validEmail(mixed $email): ?string
    {
        if (! is_string($email)) {
            return null;
        }

        $email = mb_trim($email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    private function requireValidEmail(string $email): string
    {
        $email = $this->validEmail($email);

        if ($email === null) {
            throw ValidationException::withMessages(['recipient' => 'Enter a valid recipient email address.']);
        }

        return $email;
    }

    /** @param array<string, mixed> $value */
    private function canonicalize(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonicalize($item);
            }
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    /** @param array<string, mixed> $snapshot */
    private function signaturePayload(array $snapshot): string
    {
        return json_encode(
            $this->canonicalize($snapshot),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );
    }
}
