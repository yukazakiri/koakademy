<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\FinancialDocumentStatus;
use App\Enums\FinancialDocumentType;
use App\Models\FinancialDocumentIssuance;
use App\Services\FinancialDocumentService;
use Inertia\Inertia;
use Inertia\Response;

final class FinancialDocumentVerificationController extends Controller
{
    public function __invoke(string $token, FinancialDocumentService $documents): Response
    {
        $issuance = FinancialDocumentIssuance::query()
            ->where('verification_token_hash', hash('sha256', $token))
            ->first();

        if (! $issuance) {
            return Inertia::render('finance/verify-document', ['status' => 'invalid']);
        }

        $snapshot = $issuance->snapshot;
        $studentName = (string) data_get($snapshot, $issuance->type === FinancialDocumentType::Receipt ? 'student_name' : 'student.name', '');
        $studentNumber = (string) data_get($snapshot, $issuance->type === FinancialDocumentType::Receipt ? 'student_id' : 'student.student_id', '');
        $valid = $documents->hasValidIntegrity($issuance);

        return Inertia::render('finance/verify-document', [
            'status' => $valid
                ? ($issuance->status === FinancialDocumentStatus::Revoked ? 'revoked' : 'valid')
                : 'integrity_failed',
            'document' => [
                'type' => $issuance->type->label(),
                'document_number' => $issuance->document_number,
                'student' => $this->maskName($studentName),
                'student_number' => $this->maskNumber($studentNumber),
                'issued_at' => $issuance->issued_at?->format('F d, Y h:i A'),
                'institution' => data_get($snapshot, 'institution.name', config('app.name')),
            ],
        ]);
    }

    private function maskName(string $name): string
    {
        return collect(preg_split('/\s+/', mb_trim($name)) ?: [])
            ->map(fn (string $part): string => mb_substr($part, 0, 1).str_repeat('*', max(2, mb_strlen($part) - 1)))
            ->implode(' ');
    }

    private function maskNumber(string $number): string
    {
        return mb_strlen($number) <= 4
            ? str_repeat('*', mb_strlen($number))
            : mb_substr($number, 0, 2).str_repeat('*', mb_strlen($number) - 4).mb_substr($number, -2);
    }
}
