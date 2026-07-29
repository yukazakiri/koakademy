<?php

declare(strict_types=1);

namespace App\Enrollment;

use App\Data\Enrollment\ActionResult;
use App\Data\Enrollment\EnrollmentContext;
use App\Enums\PaymentMethod;
use App\Models\AdminTransaction;
use App\Models\StudentEnrollment;
use App\Models\StudentTransaction;
use App\Models\Transaction;
use App\Services\EnrollmentBillingService;
use App\Services\FinancialDocumentService;

final readonly class EnrollmentPaymentService
{
    public function __construct(
        private EnrollmentBillingService $billing,
        private FinancialDocumentService $financialDocuments,
    ) {}

    /** @param array<string, mixed> $configuration */
    public function record(
        EnrollmentContext $context,
        array $configuration,
        string $idempotencyKey,
    ): ActionResult {
        $enrollment = $context->enrollment;
        if (! $enrollment instanceof StudentEnrollment || ! $enrollment->exists) {
            return ActionResult::failure('Payment verification requires a persisted enrollment.');
        }

        $payload = is_array($configuration['runtime_payload'] ?? null)
            ? $configuration['runtime_payload']
            : [];
        $billing = $context->pinnedPolicyConfiguration['billing'] ?? [];
        $receiptMode = (string) ($configuration['receipt_mode'] ?? data_get($billing, 'configuration.receipt_mode', 'required'));
        if (! in_array($receiptMode, ['required', 'optional', 'none'], true)) {
            return ActionResult::failure('Receipt mode must be required, optional, or none.');
        }

        $withoutReceipt = (bool) ($payload['without_receipt'] ?? false);
        $reason = mb_trim((string) ($payload['reason'] ?? ''));
        $allowNoReceipt = $receiptMode === 'none' || (bool) ($configuration['allow_no_receipt'] ?? false);
        if ($withoutReceipt && ! $allowNoReceipt) {
            return ActionResult::failure('This policy does not authorize payment verification without a receipt.');
        }
        if (($withoutReceipt || $receiptMode === 'none') && $reason === '') {
            return ActionResult::failure('A reason is required for payment verification without a receipt.');
        }

        $recordTransaction = (bool) ($configuration['record_transaction'] ?? ($receiptMode !== 'none' && ! $withoutReceipt));
        if (! $recordTransaction) {
            return ActionResult::success([
                'verified' => 'payment',
                'receipt_mode' => $receiptMode,
                'recorded_transaction' => false,
                'reason' => $reason,
            ]);
        }

        if (! $context->actor) {
            return ActionResult::failure('A verified actor is required to record an enrollment payment.');
        }

        $invoiceNumber = mb_trim((string) ($payload['invoicenumber'] ?? ''));
        if ($receiptMode === 'required' && $invoiceNumber === '') {
            return ActionResult::failure('Invoice number is required.');
        }

        $paymentMethod = (string) ($payload['payment_method'] ?? PaymentMethod::Cash->value);
        $validMethods = array_map(
            fn (PaymentMethod $method): string => $method->value,
            PaymentMethod::cases(),
        );
        if (! in_array($paymentMethod, $validMethods, true)) {
            return ActionResult::failure('The selected payment method is not supported.');
        }
        $allowedMethods = is_array($billing['allowed_payment_methods'] ?? null)
            ? $billing['allowed_payment_methods']
            : [];
        if ($allowedMethods !== [] && ! in_array($paymentMethod, $allowedMethods, true)) {
            return ActionResult::failure('The selected payment method is not allowed by this enrollment policy.');
        }

        $settlements = is_array($payload['settlements'] ?? null) ? $payload['settlements'] : [];
        $tuitionPayment = (float) ($settlements['tuition_fee'] ?? 0);
        $minimum = $this->minimumPayment($enrollment, $billing);
        if ($tuitionPayment < $minimum) {
            return ActionResult::failure('The tuition payment does not meet the configured minimum of '.number_format($minimum, 2).'.');
        }

        $separateFees = $enrollment->additionalFees()
            ->where('is_separate_transaction', true)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $separateInvoiceNumbers = [];
        foreach ($separateFees as $fee) {
            $field = "separate_fee_{$fee->id}_transaction";
            $value = $payload[$field] ?? null;
            if ($value !== null && ! is_string($value) && ! is_int($value)) {
                return ActionResult::failure("Transaction number for {$fee->fee_name} must be text.");
            }

            $separateInvoiceNumbers[$fee->id] = mb_trim((string) $value);
            if ($receiptMode === 'required' && $separateInvoiceNumbers[$fee->id] === '') {
                return ActionResult::failure("Transaction number is required for {$fee->fee_name}.");
            }
        }

        $separateTransactions = [];
        foreach ($separateFees as $fee) {
            $feeIdempotencyKey = hash('sha256', "{$idempotencyKey}:additional-fee:{$fee->id}");
            $invoiceNumberForFee = $separateInvoiceNumbers[$fee->id];
            $link = StudentTransaction::query()
                ->where('student_enrollment_id', $enrollment->id)
                ->where('idempotency_key', $feeIdempotencyKey)
                ->with('transaction')
                ->first();
            $alreadyExists = $link instanceof StudentTransaction;
            if (! $link instanceof StudentTransaction) {
                $link = $this->createLinkedTransaction(
                    enrollment: $enrollment,
                    actorId: $context->actor->id,
                    description: "Payment for {$fee->fee_name}",
                    adminDescription: 'Enrollment additional fee payment',
                    paymentMethod: $paymentMethod,
                    settlements: ['others' => (float) $fee->amount],
                    invoiceNumber: $invoiceNumberForFee,
                    signature: $payload['signature'] ?? null,
                    idempotencyKey: $feeIdempotencyKey,
                );
            }

            $persistedInvoiceNumber = is_string($link->transaction->invoicenumber)
                ? $link->transaction->invoicenumber
                : ($invoiceNumberForFee === '' ? null : $invoiceNumberForFee);
            if ($fee->transaction_number !== $persistedInvoiceNumber) {
                $fee->update(['transaction_number' => $persistedInvoiceNumber]);
            }
            $separateTransactions[] = [
                'additional_fee_id' => $fee->id,
                'student_transaction_id' => $link->id,
                'transaction_id' => $link->transaction_id,
                'invoice_number' => $persistedInvoiceNumber,
                'already_exists' => $alreadyExists,
            ];
        }

        $studentTransaction = StudentTransaction::query()
            ->where('student_enrollment_id', $enrollment->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        $alreadyExists = $studentTransaction instanceof StudentTransaction;
        if (! $studentTransaction instanceof StudentTransaction) {
            $studentTransaction = $this->createLinkedTransaction(
                enrollment: $enrollment,
                actorId: $context->actor->id,
                description: (string) ($configuration['description'] ?? 'Enrollment tuition payment'),
                adminDescription: 'Enrollment tuition payment',
                paymentMethod: $paymentMethod,
                settlements: $settlements,
                invoiceNumber: $invoiceNumber,
                signature: $payload['signature'] ?? null,
                idempotencyKey: $idempotencyKey,
            );
        }

        if ($enrollment->studentTuition) {
            $this->billing->syncTuitionBalance($enrollment->studentTuition->refresh());
        }

        return ActionResult::success([
            'verified' => 'payment',
            'student_transaction_id' => $studentTransaction->id,
            'transaction_id' => $studentTransaction->transaction_id,
            'separate_transactions' => $separateTransactions,
            'separate_transaction_count' => count($separateTransactions),
            'receipt_mode' => $receiptMode,
            'recorded_transaction' => true,
            'already_exists' => $alreadyExists,
        ]);
    }

    /** @return array{transactions:int, amount:float, documents_revoked:int} */
    public function reverseLinked(StudentEnrollment $enrollment): array
    {
        $links = $enrollment->enrollmentTransactions()->lockForUpdate()->get();
        $amount = (float) $links->sum('amount');
        $transactionIds = $links->pluck('transaction_id')->filter()->unique()->values();
        $documentsRevoked = $this->financialDocuments->revokeForTransactions(
            $transactionIds,
            'The related enrollment payment was reversed.',
        );

        $links->each->delete();
        foreach ($transactionIds as $transactionId) {
            if (StudentTransaction::query()->where('transaction_id', $transactionId)->exists()) {
                continue;
            }
            AdminTransaction::query()->where('transaction_id', $transactionId)->delete();
            Transaction::query()->whereKey($transactionId)->delete();
        }

        if ($enrollment->studentTuition) {
            $this->billing->syncTuitionBalance($enrollment->studentTuition->refresh());
        }
        $enrollment->additionalFees()
            ->where('is_separate_transaction', true)
            ->whereNotNull('transaction_number')
            ->update(['transaction_number' => null]);

        return [
            'transactions' => $transactionIds->count(),
            'amount' => $amount,
            'documents_revoked' => $documentsRevoked,
        ];
    }

    /**
     * @param  array<string, mixed>  $settlements
     */
    private function createLinkedTransaction(
        StudentEnrollment $enrollment,
        int $actorId,
        string $description,
        string $adminDescription,
        string $paymentMethod,
        array $settlements,
        string $invoiceNumber,
        mixed $signature,
        string $idempotencyKey,
    ): StudentTransaction {
        $transaction = Transaction::query()->create([
            'description' => $description,
            'payment_method' => $paymentMethod,
            'settlements' => $settlements,
            'status' => 'Paid',
            'transaction_date' => now(),
            'invoicenumber' => $invoiceNumber === '' ? null : $invoiceNumber,
            'signature' => $signature,
            'user_id' => $actorId,
        ]);
        $amount = (float) collect($settlements)
            ->map(fn (mixed $value): float => (float) $value)
            ->filter(fn (float $value): bool => $value > 0)
            ->sum();

        $studentTransaction = StudentTransaction::query()->create([
            'student_id' => $enrollment->student_id,
            'student_enrollment_id' => $enrollment->id,
            'transaction_id' => $transaction->id,
            'amount' => $amount,
            'status' => $transaction->status,
            'idempotency_key' => $idempotencyKey,
        ]);
        AdminTransaction::query()->create([
            'admin_id' => $actorId,
            'transaction_id' => $transaction->id,
            'amount' => $amount,
            'type' => 'credit',
            'description' => $adminDescription,
            'status' => $transaction->status,
        ]);

        return $studentTransaction->setRelation('transaction', $transaction);
    }

    /** @param array<string, mixed> $billing */
    private function minimumPayment(StudentEnrollment $enrollment, array $billing): float
    {
        $overallTuition = (float) $enrollment->studentTuition()->value('overall_tuition');
        $configuration = is_array($billing['configuration'] ?? null) ? $billing['configuration'] : [];
        $minimum = is_array($configuration['minimum_payment'] ?? null)
            ? $configuration['minimum_payment']
            : [
                'type' => $configuration['minimum_payment_type'] ?? 'none',
                'value' => $configuration['minimum_payment_value'] ?? 0,
            ];

        return match ((string) ($minimum['type'] ?? 'none')) {
            'fixed' => max(0.0, (float) ($minimum['value'] ?? 0)),
            'percentage' => max(0.0, $overallTuition)
                * min(100.0, max(0.0, (float) ($minimum['value'] ?? 0))) / 100,
            default => 0.0,
        };
    }
}
