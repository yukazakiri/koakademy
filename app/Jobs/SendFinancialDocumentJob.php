<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\FinancialDeliveryStatus;
use App\Enums\FinancialDocumentStatus;
use App\Enums\FinancialDocumentType;
use App\Mail\FinancialDocumentMail;
use App\Models\FinancialDocumentDelivery;
use App\Models\FinancialDocumentIssuance;
use App\Models\Transaction;
use App\Services\FinancialDocumentViewDataService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;
use Throwable;

final class SendFinancialDocumentJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $deliveryId)
    {
        $this->onConnection((string) config('queue.receipt_email_connection', 'redis-pdf'));
        $this->onQueue((string) config('queue.receipt_email_queue', 'pdf-generation'));
    }

    public function uniqueId(): string
    {
        return (string) $this->deliveryId;
    }

    public function handle(FinancialDocumentViewDataService $viewDataService): void
    {
        $delivery = FinancialDocumentDelivery::query()->with('issuance')->findOrFail($this->deliveryId);
        $issuance = $delivery->issuance;

        if (
            in_array($delivery->status, [FinancialDeliveryStatus::Sent, FinancialDeliveryStatus::Cancelled], true)
            || $issuance->status === FinancialDocumentStatus::Revoked
        ) {
            return;
        }

        $delivery->update([
            'status' => FinancialDeliveryStatus::Processing,
            'attempts' => $delivery->attempts + 1,
            'error' => null,
        ]);

        $document = $viewDataService->build($issuance);
        $pdfBytes = $this->pdfBytes($issuance, $document);
        $delivery->refresh();
        $issuance->refresh();

        if (
            $delivery->status === FinancialDeliveryStatus::Cancelled
            || $issuance->status === FinancialDocumentStatus::Revoked
        ) {
            return;
        }

        Mail::to($delivery->recipient)->send(
            new FinancialDocumentMail($issuance->type, $document, $pdfBytes),
        );

        $delivery->refresh();
        $issuance->refresh();

        if (
            $delivery->status === FinancialDeliveryStatus::Cancelled
            || $issuance->status === FinancialDocumentStatus::Revoked
        ) {
            return;
        }

        $now = now();
        $delivery->update([
            'status' => FinancialDeliveryStatus::Sent,
            'sent_at' => $now,
            'failed_at' => null,
            'error' => null,
        ]);
        $issuance->update([
            'status' => FinancialDocumentStatus::Sent,
            'recipient' => $delivery->recipient,
            'sent_at' => $now,
            'failed_at' => null,
            'failure_message' => null,
        ]);

        if ($issuance->transaction_id) {
            Transaction::query()->whereKey($issuance->transaction_id)->update([
                'receipt_email_status' => 'sent',
                'receipt_email_recipient' => $delivery->recipient,
                'receipt_emailed_at' => $now,
                'receipt_email_failed_at' => null,
                'receipt_email_error' => null,
            ]);
        }
    }

    public function failed(Throwable $throwable): void
    {
        $delivery = FinancialDocumentDelivery::query()->with('issuance')->find($this->deliveryId);
        if (! $delivery) {
            return;
        }
        if (
            $delivery->status === FinancialDeliveryStatus::Cancelled
            || $delivery->issuance->status === FinancialDocumentStatus::Revoked
        ) {
            return;
        }

        Log::error('Official financial document delivery failed permanently.', [
            'delivery_id' => $this->deliveryId,
            'exception_class' => $throwable::class,
        ]);

        $message = 'The document email could not be delivered after multiple attempts.';
        $delivery->update([
            'status' => FinancialDeliveryStatus::Failed,
            'failed_at' => now(),
            'error' => $message,
        ]);
        $delivery->issuance->update([
            'status' => FinancialDocumentStatus::Failed,
            'failed_at' => now(),
            'failure_message' => $message,
        ]);

        if ($delivery->issuance->transaction_id) {
            Transaction::query()->whereKey($delivery->issuance->transaction_id)->update([
                'receipt_email_status' => 'failed',
                'receipt_email_recipient' => $delivery->recipient,
                'receipt_email_failed_at' => now(),
                'receipt_email_error' => $message,
            ]);
        }
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 30, 120];
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function pdfBytes(FinancialDocumentIssuance $issuance, array $document): string
    {
        if ($issuance->disk && $issuance->pdf_path && Storage::disk($issuance->disk)->exists($issuance->pdf_path)) {
            $pdfBytes = Storage::disk($issuance->disk)->get($issuance->pdf_path);

            if (
                $issuance->pdf_checksum === null
                || ! hash_equals($issuance->pdf_checksum, hash('sha256', $pdfBytes))
            ) {
                throw new RuntimeException('The stored financial document failed its integrity check.');
            }

            return $pdfBytes;
        }

        $view = $issuance->type === FinancialDocumentType::Receipt
            ? 'pdf.financial-document-receipt'
            : 'pdf.financial-document-invoice';
        $pdfBytes = base64_decode(
            Pdf::view($view, ['financialDocument' => $document])
                ->format(Format::A4)
                ->base64(),
            true,
        );

        if ($pdfBytes === false) {
            throw new RuntimeException('Unable to generate the official financial document PDF.');
        }

        $disk = 'private';
        $path = sprintf(
            'financial-documents/%s/%s.pdf',
            $issuance->type->value,
            $issuance->uuid,
        );

        if (! Storage::disk($disk)->put($path, $pdfBytes)) {
            throw new RuntimeException('Unable to store the official financial document PDF.');
        }

        $issuance->update([
            'disk' => $disk,
            'pdf_path' => $path,
            'pdf_checksum' => hash('sha256', $pdfBytes),
        ]);

        return $pdfBytes;
    }
}
