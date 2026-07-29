<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FinancialDocumentType;
use App\Models\FinancialDocumentIssuance;
use App\Models\StudentTransaction;
use App\Models\Transaction;

final readonly class TransactionReceiptDataService
{
    public function __construct(private GeneralSettingsService $settingsService) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Transaction $transaction, ?StudentTransaction $studentTransaction = null): array
    {
        $transaction->loadMissing(['student.Course', 'studentTransactions', 'user']);
        $studentTransaction ??= $transaction->studentTransactions->first();
        $studentTransaction?->loadMissing('student.Course');
        $student = $studentTransaction?->student ?? $transaction->student->first();
        $settings = $this->settingsService->getGlobalSettingsModel();
        $issuance = FinancialDocumentIssuance::query()
            ->where('type', FinancialDocumentType::Receipt->value)
            ->where('transaction_id', $transaction->id)
            ->first();
        $items = collect($transaction->settlements ?? [])
            ->map(fn (mixed $amount): float => (float) $amount)
            ->filter(fn (float $amount): bool => $amount > 0)
            ->all();
        $displayedTotal = (float) array_sum($items);

        return [
            'id' => $transaction->id,
            'transaction_number' => $transaction->transaction_number,
            'reference_number' => $transaction->invoicenumber,
            'date' => $transaction->transaction_date?->format('F d, Y'),
            'time' => $transaction->transaction_date?->format('h:i A'),
            'issued_at' => $transaction->transaction_date?->toIso8601String(),
            'student_name' => $student?->full_name ?? 'N/A',
            'student_id' => $student?->student_id ?? 'N/A',
            'student_email' => $student?->email,
            'student_course' => $student?->Course?->code ?? 'N/A',
            'student_year_level' => $student?->academic_year,
            'amount' => $displayedTotal > 0
                ? $displayedTotal
                : (float) ($studentTransaction?->amount ?? $transaction->raw_total_amount),
            'method' => $transaction->payment_method,
            'status' => $studentTransaction?->status ?? $transaction->status,
            'items' => $items,
            'cashier' => $transaction->user?->name ?? 'System',
            'remarks' => $transaction->description,
            'currency' => $this->settingsService->getCurrency(),
            'institution' => [
                'name' => $settings?->school_portal_title ?: config('app.name'),
                'description' => $settings?->school_portal_description,
                'support_email' => $settings?->support_email,
                'support_phone' => $settings?->support_phone,
            ],
            'email_delivery' => [
                'status' => $issuance?->status->value ?? $transaction->receipt_email_status,
                'recipient' => $issuance?->recipient ?? $transaction->receipt_email_recipient,
                'sent_at' => $issuance?->sent_at?->format('M d, Y h:i A') ?? $transaction->receipt_emailed_at?->format('M d, Y h:i A'),
                'failed_at' => $issuance?->failed_at?->format('M d, Y h:i A') ?? $transaction->receipt_email_failed_at?->format('M d, Y h:i A'),
                'error' => $issuance?->failure_message ?? $transaction->receipt_email_error,
            ],
            'official_document' => $issuance ? [
                'number' => $issuance->document_number,
                'verification_url' => route('finance-documents.verify', ['token' => $issuance->verification_token]),
                'download_url' => $issuance->pdf_path
                    ? route('administrators.finance.documents.download', $issuance, false)
                    : null,
            ] : null,
        ];
    }
}
