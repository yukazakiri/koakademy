<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FinancialDocumentIssuance;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

final readonly class FinancialDocumentViewDataService
{
    /** @return array<string, mixed> */
    public function build(FinancialDocumentIssuance $issuance): array
    {
        $verificationUrl = route('finance-documents.verify', ['token' => $issuance->verification_token]);

        return $issuance->snapshot + [
            'document' => [
                'uuid' => $issuance->uuid,
                'type' => $issuance->type->value,
                'label' => $issuance->type->label(),
                'number' => $issuance->document_number,
                'paper_reference' => $issuance->paper_reference,
                'issued_at' => $issuance->issued_at?->format('F d, Y h:i A'),
                'verification_url' => $verificationUrl,
                'verification_code' => mb_strtoupper(mb_substr(str_replace('-', '', $issuance->uuid), 0, 12)),
                'qr_code' => $this->qrCode($verificationUrl),
            ],
        ];
    }

    private function qrCode(string $url): string
    {
        $result = new Builder(
            writer: new PngWriter(),
            writerOptions: [],
            validateResult: false,
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 280,
            margin: 4,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        )->build();

        return 'data:image/png;base64,'.base64_encode($result->getString());
    }
}
