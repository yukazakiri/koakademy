<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\StatementOfAccountIssuance;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Models\User;
use App\Settings\SiteSettings;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

final readonly class StatementOfAccountService
{
    public function __construct(
        private SchoolBrandingService $branding,
        private SiteSettings $siteSettings,
        private ?EnrollmentBillingService $billing = null,
    ) {}

    /** @return array<string, mixed> */
    public function build(Student $student, string $schoolYear, int $semester): array
    {
        $student->loadMissing('Course');
        $tuition = StudentTuition::query()
            ->with('enrollment.student')
            ->where('student_id', $student->id)
            ->where('semester', $semester)
            ->where('school_year', $schoolYear)
            ->first();

        $enrollment = $tuition?->enrollment;
        if (! $enrollment) {
            $enrollment = StudentEnrollment::query()
                ->where('student_id', $student->id)
                ->where('semester', $semester)
                ->where('school_year', $schoolYear)
                ->first();
        }

        if (! $tuition && $enrollment) {
            $tuition = StudentTuition::query()->where('enrollment_id', $enrollment->id)->first();
        }

        $transactions = collect();
        if ($enrollment) {
            $enrollment->setRelation('student', $student);
            $transactions = $enrollment->enrollmentTransactions()->with('transaction')->get();
        }

        $payments = $transactions->map(function ($entry): array {
            $transaction = $entry->relationLoaded('transaction') && $entry->transaction ? $entry->transaction : $entry;
            $amount = $entry->amount ?? $transaction->raw_total_amount ?? $transaction->total_amount ?? 0;

            return [
                'id' => (int) $entry->id,
                'date' => ($transaction->transaction_date ?? $transaction->created_at ?? $entry->created_at)?->format('M d, Y'),
                'description' => $transaction->description ?? 'Tuition Payment',
                'amount' => (float) $amount,
                'invoice' => $transaction->invoicenumber ?? null,
                'method' => $transaction->payment_method ?? null,
            ];
        })->values()->all();

        $billing = $this->billing ?? app(EnrollmentBillingService::class);
        $assessment = (float) ($tuition?->overall_tuition ?? 0);
        $totalPaid = $tuition ? $billing->totalPaid($tuition) : 0.0;
        $position = $tuition ? $billing->accountPosition($tuition, $totalPaid) : ['balance_due' => 0.0, 'credit' => 0.0];
        $balance = (float) $position['balance_due'];
        $credit = (float) $position['credit'];

        return [
            'student' => [
                'id' => (int) $student->id,
                'student_no' => (string) ($student->student_id ?: $student->id),
                'name' => $student->full_name ?? $student->name,
                'course' => $student->Course?->title ?? $student->Course?->code ?? 'N/A',
            ],
            'enrollment_id' => $enrollment?->id,
            'tuition_id' => $tuition?->id,
            'filters' => ['semester' => $semester, 'school_year' => $schoolYear],
            'school' => $this->branding->resolve(),
            'currency_code' => $this->siteSettings->getCurrency(),
            'currency_symbol' => match ($this->siteSettings->getCurrency()) {
                'USD' => '$',
                'EUR' => '€',
                default => '₱',
            },
            'tuition' => $tuition ? [
                'total_lectures' => (float) $tuition->total_lectures,
                'total_laboratory' => (float) $tuition->total_laboratory,
                'total_tuition' => (float) $tuition->total_tuition,
                'total_miscelaneous_fees' => (float) $tuition->total_miscelaneous_fees,
                'discount' => (float) $tuition->discount,
                'overall_tuition' => $assessment,
                'total_paid' => $totalPaid,
                'total_balance' => $balance,
                'credit' => $credit,
            ] : null,
            'transactions' => $payments,
            'generated_at' => now()->format('F d, Y h:i A'),
        ];
    }

    /** @return array{issuance: StatementOfAccountIssuance, view_data: array<string, mixed>} */
    public function issue(Student $student, User $issuer, string $schoolYear, int $semester): array
    {
        $snapshot = $this->build($student, $schoolYear, $semester);
        $uuid = (string) Str::uuid();
        $token = Str::random(64);
        $documentNumber = sprintf('SOA-%s-%s', now()->format('Ymd'), mb_strtoupper(mb_substr(str_replace('-', '', $uuid), 0, 10)));

        $issuance = StatementOfAccountIssuance::query()->create([
            'uuid' => $uuid,
            'student_id' => $student->id,
            'enrollment_id' => $snapshot['enrollment_id'],
            'tuition_id' => $snapshot['tuition_id'],
            'issued_by' => $issuer->id,
            'document_number' => $documentNumber,
            'verification_token_hash' => hash('sha256', $token),
            'integrity_signature' => $this->sign($snapshot),
            'snapshot' => $snapshot,
            'status' => 'pending',
            'issued_at' => now(),
        ]);

        $verificationUrl = URL::route('soa.verify', ['token' => $token]);
        $viewData = $snapshot + [
            'document_number' => $documentNumber,
            'verification_url' => $verificationUrl,
            'verification_code' => mb_strtoupper(mb_substr(str_replace('-', '', $uuid), 0, 12)),
            'qr_code' => $this->qrCode($verificationUrl),
            'official' => true,
        ];

        return ['issuance' => $issuance, 'view_data' => $viewData];
    }

    /** @param array<string, mixed> $snapshot */
    public function sign(array $snapshot): string
    {
        $canonical = $this->canonicalize($snapshot);
        $key = (string) config('app.key');

        return hash_hmac('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), $key);
    }

    public function hasValidIntegrity(StatementOfAccountIssuance $issuance): bool
    {
        return hash_equals($issuance->integrity_signature, $this->sign($issuance->snapshot));
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

    private function qrCode(string $url): string
    {
        $result = new Builder(
            writer: new PngWriter(),
            writerOptions: [],
            validateResult: false,
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 300,
            margin: 4,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        )->build();

        return 'data:image/png;base64,'.base64_encode($result->getString());
    }
}
