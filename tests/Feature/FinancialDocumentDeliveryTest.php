<?php

declare(strict_types=1);

use App\Enums\FinancialDocumentStatus;
use App\Enums\FinancialDocumentType;
use App\Enums\UserRole;
use App\Jobs\SendFinancialDocumentJob;
use App\Mail\FinancialDocumentMail;
use App\Models\AdditionalFee;
use App\Models\FinancialDocumentIssuance;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTransaction;
use App\Models\StudentTuition;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FinancialDocumentService;
use App\Services\FinancialDocumentViewDataService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Modules\Cashier\Filament\Pages\Cashier;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function grantFinancialDocumentFinanceAccess(User $user): void
{
    Permission::findOrCreate('View:Cashier', 'web');
    $role = Role::findOrCreate($user->role->value, 'web');
    $role->syncPermissions(['View:Cashier']);
}

function enableFinancialDocumentMail(): GeneralSetting
{
    $settings = GeneralSetting::query()->firstOrCreate([], ['site_name' => 'KoAcademy']);
    $moreConfigs = $settings->more_configs ?? [];
    $moreConfigs['notification_channels'] = ['enabled_channels' => ['mail']];
    $moreConfigs['finance_documents'] = [
        'automatic_receipts_enabled' => true,
        'require_paper_or_reference' => true,
        'manual_invoices_enabled' => true,
    ];
    $settings->update([
        'email_from_address' => 'billing@example.test',
        'more_configs' => $moreConfigs,
    ]);

    return $settings;
}

it('attributes Cashier page receipts to the authenticated operator before the admin ledger is written', function (): void {
    enableFinancialDocumentMail();
    $cashier = User::factory()->create(['role' => UserRole::Cashier]);
    $student = Student::factory()->create(['email' => 'cashier-flow@example.test']);
    Bus::fake();
    $this->actingAs($cashier);
    $page = app(Cashier::class);
    $page->data = [
        'selectedStudent' => $student,
        'description' => 'Cashier page payment',
        'selectedSchoolYear' => '2026 - 2027',
        'selectedSemester' => 1,
        'settlements' => ['others' => 500],
        'invoicenumber' => 'OR-CASHIER-PAGE-1',
        'signature' => null,
    ];

    $page->create();

    $transaction = Transaction::query()->sole();
    $receipt = FinancialDocumentIssuance::query()->sole();
    expect($transaction->user_id)->toBe($cashier->id)
        ->and($receipt->issued_by)->toBe($cashier->id)
        ->and($receipt->snapshot['cashier'])->toBe($cashier->name);
});

it('freezes the receipt total from every displayed settlement row', function (): void {
    Bus::fake();
    enableFinancialDocumentMail();
    $student = Student::factory()->create(['email' => 'mixed-settlements@example.test']);
    $transaction = Transaction::query()->create([
        'description' => 'Mixed enrollment payment',
        'payment_method' => 'Cash',
        'status' => 'paid',
        'transaction_date' => now(),
        'settlements' => [
            'tuition_fee' => 500,
            'registration_fee' => 125,
            'others' => 75,
        ],
        'invoicenumber' => 'OR-MIXED-1',
    ]);
    StudentTransaction::query()->create([
        'student_id' => $student->id,
        'transaction_id' => $transaction->id,
        'amount' => 500,
        'status' => 'paid',
    ]);

    $receipt = FinancialDocumentIssuance::query()->sole();

    expect((float) collect($receipt->snapshot['items'])->sum())->toBe(700.0)
        ->and($receipt->snapshot['amount'])->toEqual(700.0);
});

it('holds a paid eReceipt until its paper OR number is recorded', function (): void {
    Bus::fake();
    enableFinancialDocumentMail();
    $cashier = User::factory()->create(['role' => UserRole::Cashier]);
    $student = Student::factory()->create(['email' => 'student@example.test']);
    grantFinancialDocumentFinanceAccess($cashier);
    $transaction = Transaction::query()->create([
        'description' => 'Tuition payment',
        'payment_method' => 'Cash',
        'status' => 'paid',
        'transaction_date' => now(),
        'settlements' => ['tuition_fee' => 1200],
        'user_id' => $cashier->id,
    ]);

    StudentTransaction::query()->create([
        'student_id' => $student->id,
        'transaction_id' => $transaction->id,
        'amount' => 1200,
        'status' => 'paid',
    ]);

    $issuance = FinancialDocumentIssuance::query()->sole();
    expect($issuance->status)->toBe(FinancialDocumentStatus::AwaitingReference)
        ->and($transaction->fresh()->receipt_email_status)->toBe('awaiting_reference');
    Bus::assertNotDispatched(SendFinancialDocumentJob::class);

    $this->actingAs($cashier)
        ->post(portalUrlForAdministrators("/administrators/finance/payments/{$transaction->id}/resend-receipt"), [
            'recipient' => 'student@example.test',
            'reference_number' => 'OR-2026-1001',
        ])
        ->assertRedirect();

    $issuance->refresh();
    expect($issuance->status)->toBe(FinancialDocumentStatus::Queued)
        ->and($issuance->paper_reference)->toBe('OR-2026-1001')
        ->and($issuance->snapshot['reference_number'])->toBe('OR-2026-1001')
        ->and($transaction->fresh()->invoicenumber)->toBe('OR-2026-1001');
    Bus::assertDispatchedTimes(SendFinancialDocumentJob::class, 1);
});

it('issues only one eReceipt for repeated processing of the same transaction', function (): void {
    Bus::fake();
    enableFinancialDocumentMail();
    $student = Student::factory()->create(['email' => 'student@example.test']);
    $transaction = Transaction::query()->create([
        'description' => 'Payment',
        'payment_method' => 'Cash',
        'status' => 'paid',
        'transaction_date' => now(),
        'settlements' => ['others' => 500],
        'invoicenumber' => 'OR-IDEMPOTENT-1',
    ]);
    $link = StudentTransaction::query()->create([
        'student_id' => $student->id,
        'transaction_id' => $transaction->id,
        'amount' => 500,
        'status' => 'paid',
    ]);

    app(FinancialDocumentService::class)->issueReceipt($link);

    expect(FinancialDocumentIssuance::query()->count())->toBe(1);
    Bus::assertDispatchedTimes(SendFinancialDocumentJob::class, 1);
});

it('does not issue receipts for non-final or zero-value transactions', function (string $status, int $amount): void {
    Bus::fake();
    enableFinancialDocumentMail();
    $student = Student::factory()->create(['email' => 'student@example.test']);
    $transaction = Transaction::query()->create([
        'description' => 'Not final',
        'payment_method' => 'Cash',
        'status' => $status,
        'transaction_date' => now(),
        'settlements' => ['others' => $amount],
        'invoicenumber' => 'OR-NOT-FINAL',
    ]);
    StudentTransaction::query()->create([
        'student_id' => $student->id,
        'transaction_id' => $transaction->id,
        'amount' => $amount,
        'status' => $status,
    ]);

    expect(FinancialDocumentIssuance::query()->count())->toBe(0);
    Bus::assertNotDispatched(SendFinancialDocumentJob::class);
})->with([
    'pending payment' => ['pending', 500],
    'cancelled payment' => ['cancelled', 500],
    'zero payment' => ['paid', 0],
]);

it('does not automatically issue receipts when automatic delivery is disabled', function (): void {
    Bus::fake();
    $settings = enableFinancialDocumentMail();
    $moreConfigs = $settings->more_configs;
    $moreConfigs['finance_documents']['automatic_receipts_enabled'] = false;
    $settings->update(['more_configs' => $moreConfigs]);
    $student = Student::factory()->create(['email' => 'student@example.test']);
    $transaction = Transaction::query()->create([
        'description' => 'Disabled automatic receipt',
        'payment_method' => 'Cash',
        'status' => 'paid',
        'transaction_date' => now(),
        'settlements' => ['others' => 500],
        'invoicenumber' => 'OR-DISABLED-1',
    ]);

    StudentTransaction::query()->create([
        'student_id' => $student->id,
        'transaction_id' => $transaction->id,
        'amount' => 500,
        'status' => 'paid',
    ]);

    expect(FinancialDocumentIssuance::query()->count())->toBe(0);
    Bus::assertNotDispatched(SendFinancialDocumentJob::class);
});

it('allows a cashier to supply an email override for a completed transaction', function (): void {
    Bus::fake();
    enableFinancialDocumentMail();
    $cashier = User::factory()->create(['role' => UserRole::Cashier]);
    grantFinancialDocumentFinanceAccess($cashier);
    $student = Student::factory()->create(['email' => null]);
    $transaction = Transaction::query()->create([
        'description' => 'Completed payment',
        'payment_method' => 'Cash',
        'status' => 'completed',
        'transaction_date' => now(),
        'settlements' => ['others' => 650],
        'invoicenumber' => 'OR-COMPLETED-1',
        'user_id' => $cashier->id,
    ]);
    StudentTransaction::query()->create([
        'student_id' => $student->id,
        'transaction_id' => $transaction->id,
        'amount' => 650,
        'status' => 'completed',
    ]);

    $issuance = FinancialDocumentIssuance::query()->sole();
    expect($issuance->status)->toBe(FinancialDocumentStatus::Skipped);
    Bus::assertNotDispatched(SendFinancialDocumentJob::class);

    $this->actingAs($cashier)
        ->post(portalUrlForAdministrators("/administrators/finance/payments/{$transaction->id}/resend-receipt"), [
            'recipient' => 'override@example.test',
            'reference_number' => 'OR-COMPLETED-1',
        ])
        ->assertRedirect();

    expect($issuance->fresh()->status)->toBe(FinancialDocumentStatus::Queued)
        ->and($issuance->fresh()->recipient)->toBe('override@example.test')
        ->and(FinancialDocumentIssuance::query()->count())->toBe(1);
    Bus::assertDispatchedTimes(SendFinancialDocumentJob::class, 1);
});

it('does not issue a receipt when its surrounding transaction rolls back', function (): void {
    Bus::fake();
    enableFinancialDocumentMail();
    $student = Student::factory()->create(['email' => 'student@example.test']);

    try {
        DB::transaction(function () use ($student): void {
            $transaction = Transaction::query()->create([
                'description' => 'Rolled back',
                'payment_method' => 'Cash',
                'status' => 'paid',
                'transaction_date' => now(),
                'settlements' => ['others' => 500],
                'invoicenumber' => 'OR-ROLLBACK',
            ]);
            StudentTransaction::query()->create([
                'student_id' => $student->id,
                'transaction_id' => $transaction->id,
                'amount' => 500,
                'status' => 'paid',
            ]);

            throw new RuntimeException('Rollback test');
        });
    } catch (RuntimeException) {
    }

    expect(FinancialDocumentIssuance::query()->count())->toBe(0);
    Bus::assertNotDispatched(SendFinancialDocumentJob::class);
});

it('manually issues one enrollment eInvoice with a frozen outstanding balance', function (): void {
    Bus::fake();
    enableFinancialDocumentMail();
    $issuer = User::factory()->create(['role' => UserRole::Cashier]);
    $student = Student::factory()->create(['email' => 'invoice@example.test']);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'semester' => 1,
        'school_year' => '2026 - 2027',
    ]);
    StudentTuition::query()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'semester' => 1,
        'school_year' => '2026 - 2027',
        'academic_year' => 1,
        'total_lectures' => 3000,
        'total_laboratory' => 1000,
        'total_miscelaneous_fees' => 1000,
        'total_tuition' => 4000,
        'overall_tuition' => 5000,
        'total_balance' => 4000,
        'paid' => 1000,
        'discount' => 0,
        'downpayment' => 1000,
        'status' => 'pending',
    ]);

    $issuance = app(FinancialDocumentService::class)->issueInvoice(
        $enrollment,
        $issuer,
        'invoice@example.test',
    );

    expect($issuance->type)->toBe(FinancialDocumentType::Invoice)
        ->and($issuance->status)->toBe(FinancialDocumentStatus::Queued)
        ->and($issuance->snapshot['billing_period']['school_year'])->toBe('2026 - 2027')
        ->and($issuance->snapshot['totals']['assessed'])->toEqual(5000.0)
        ->and($issuance->snapshot['totals']['paid'])->toEqual(1000.0)
        ->and($issuance->snapshot['totals']['balance'])->toEqual(4000.0)
        ->and($issuance->snapshot)->not->toHaveKey('due_date');
    Bus::assertDispatchedTimes(SendFinancialDocumentJob::class, 1);
});

it('reconciles separate enrollment fees and their dedicated payments on an eInvoice', function (): void {
    Bus::fake();
    enableFinancialDocumentMail();
    $issuer = User::factory()->create(['role' => UserRole::Cashier]);
    $student = Student::factory()->create(['email' => 'separate-fee@example.test']);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'semester' => 1,
        'school_year' => '2026 - 2027',
    ]);
    StudentTuition::query()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'semester' => 1,
        'school_year' => '2026 - 2027',
        'academic_year' => 1,
        'total_lectures' => 3000,
        'total_laboratory' => 1000,
        'total_miscelaneous_fees' => 1000,
        'total_tuition' => 4000,
        'overall_tuition' => 5750,
        'total_balance' => 5750,
        'paid' => 0,
        'discount' => 0,
        'downpayment' => 0,
        'status' => 'pending',
    ]);
    AdditionalFee::query()->create([
        'enrollment_id' => $enrollment->id,
        'fee_name' => 'Laboratory kit',
        'amount' => 750,
        'is_separate_transaction' => true,
        'transaction_number' => 'OR-LAB-KIT-1',
    ]);
    $tuitionPayment = Transaction::query()->create([
        'description' => 'Enrollment tuition payment',
        'payment_method' => 'Cash',
        'status' => 'paid',
        'transaction_date' => now(),
        'settlements' => ['tuition_fee' => 1000],
        'invoicenumber' => 'OR-TUITION-1',
        'user_id' => $issuer->id,
    ]);
    StudentTransaction::query()->create([
        'student_id' => $student->id,
        'student_enrollment_id' => $enrollment->id,
        'transaction_id' => $tuitionPayment->id,
        'amount' => 1000,
        'status' => 'paid',
    ]);
    $separateFeePayment = Transaction::query()->create([
        'description' => 'Payment for Laboratory kit',
        'payment_method' => 'Cash',
        'status' => 'paid',
        'transaction_date' => now(),
        'settlements' => ['others' => 750],
        'invoicenumber' => 'OR-LAB-KIT-1',
        'user_id' => $issuer->id,
    ]);
    StudentTransaction::query()->create([
        'student_id' => $student->id,
        'student_enrollment_id' => $enrollment->id,
        'transaction_id' => $separateFeePayment->id,
        'amount' => 750,
        'status' => 'paid',
    ]);

    $invoice = app(FinancialDocumentService::class)->issueInvoice(
        $enrollment->fresh(),
        $issuer,
        'separate-fee@example.test',
    );
    $charges = collect($invoice->snapshot['charges']);
    $payments = collect($invoice->snapshot['payments']);

    expect($charges->firstWhere('label', 'Laboratory kit')['amount'])->toEqual(750.0)
        ->and((float) $charges->sum('amount'))->toEqual(5750.0)
        ->and((float) $payments->sum('amount'))->toEqual(1750.0)
        ->and($invoice->snapshot['totals']['assessed'])->toEqual(5750.0)
        ->and($invoice->snapshot['totals']['paid'])->toEqual(1750.0)
        ->and($invoice->snapshot['totals']['balance'])->toEqual(4000.0);
});

it('renders a negative assessment adjustment on an official eInvoice', function (): void {
    Bus::fake();
    enableFinancialDocumentMail();
    $issuer = User::factory()->create(['role' => UserRole::Cashier]);
    $student = Student::factory()->create(['email' => 'adjustment@example.test']);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'semester' => 1,
        'school_year' => '2026 - 2027',
    ]);
    StudentTuition::query()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'semester' => 1,
        'school_year' => '2026 - 2027',
        'academic_year' => 1,
        'total_lectures' => 3000,
        'total_laboratory' => 1000,
        'total_miscelaneous_fees' => 1000,
        'total_tuition' => 4000,
        'overall_tuition' => 4500,
        'total_balance' => 4500,
        'paid' => 0,
        'discount' => 0,
        'downpayment' => 0,
        'status' => 'pending',
    ]);

    $invoice = app(FinancialDocumentService::class)->issueInvoice(
        $enrollment,
        $issuer,
        'adjustment@example.test',
    );
    $document = app(FinancialDocumentViewDataService::class)->build($invoice);
    $rendered = view('pdf.financial-document-invoice', [
        'financialDocument' => $document,
    ])->render();
    $adjustment = collect($invoice->snapshot['charges'])
        ->firstWhere('label', 'Assessment adjustment');
    $formatter = new NumberFormatter('en_PH', NumberFormatter::CURRENCY);
    $formattedAdjustment = $formatter->formatCurrency(-500, 'PHP');

    expect($adjustment['amount'])->toEqual(-500.0)
        ->and((float) collect($invoice->snapshot['charges'])->sum('amount'))->toBe(4500.0)
        ->and($rendered)->toContain('Assessment adjustment', $formattedAdjustment);
});

it('rejects paid enrollments and preserves older invoice snapshots when a balance changes', function (): void {
    Bus::fake();
    enableFinancialDocumentMail();
    $issuer = User::factory()->create(['role' => UserRole::Cashier]);
    $student = Student::factory()->create(['email' => 'invoice@example.test']);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'semester' => 1,
        'school_year' => '2026 - 2027',
    ]);
    $tuition = StudentTuition::query()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'semester' => 1,
        'school_year' => '2026 - 2027',
        'academic_year' => 1,
        'total_lectures' => 5000,
        'total_laboratory' => 0,
        'total_miscelaneous_fees' => 0,
        'total_tuition' => 5000,
        'overall_tuition' => 5000,
        'total_balance' => 0,
        'paid' => 5000,
        'discount' => 0,
        'downpayment' => 0,
        'status' => 'paid',
    ]);
    $documents = app(FinancialDocumentService::class);

    expect(fn () => $documents->issueInvoice($enrollment, $issuer, 'invoice@example.test'))
        ->toThrow(ValidationException::class, 'outstanding balance');

    $tuition->update(['paid' => 1000, 'total_balance' => 4000, 'status' => 'pending']);
    $first = $documents->issueInvoice($enrollment->fresh(), $issuer, 'invoice@example.test');
    $firstSnapshot = $first->snapshot;

    $tuition->update([
        'total_lectures' => 6000,
        'total_tuition' => 6000,
        'overall_tuition' => 6000,
        'total_balance' => 5000,
    ]);
    $second = $documents->issueInvoice($enrollment->fresh(), $issuer, 'invoice@example.test');

    expect($first->fresh()->snapshot)->toBe($firstSnapshot)
        ->and($first->document_number)->not->toBe($second->document_number)
        ->and($first->snapshot['totals']['balance'])->toEqual(4000.0)
        ->and($second->snapshot['totals']['balance'])->toEqual(5000.0)
        ->and(FinancialDocumentIssuance::query()->where('enrollment_id', $enrollment->id)->count())->toBe(2);
});

it('stores and emails the official PDF while preserving an audit checksum', function (): void {
    Bus::fake();
    Mail::fake();
    Storage::fake('private');
    enableFinancialDocumentMail();
    $cashier = User::factory()->create(['role' => UserRole::Cashier]);
    grantFinancialDocumentFinanceAccess($cashier);
    $student = Student::factory()->create(['email' => 'attached@example.test']);
    $transaction = Transaction::query()->create([
        'description' => 'Attachment test',
        'payment_method' => 'Cash',
        'status' => 'paid',
        'transaction_date' => now(),
        'settlements' => ['tuition_fee' => 1250],
        'invoicenumber' => 'OR-ATTACH-1',
        'user_id' => $cashier->id,
    ]);
    StudentTransaction::query()->create([
        'student_id' => $student->id,
        'transaction_id' => $transaction->id,
        'amount' => 1250,
        'status' => 'paid',
    ]);
    $issuance = FinancialDocumentIssuance::query()->with('deliveries')->sole();
    $delivery = $issuance->deliveries->sole();

    $pdfBuilder = Mockery::mock(PdfBuilder::class);
    $pdfBuilder->shouldReceive('format')->once()->with(Format::A4)->andReturnSelf();
    $pdfBuilder->shouldReceive('base64')->once()->andReturn(base64_encode('%PDF-1.4 official document'));
    Pdf::shouldReceive('view')
        ->once()
        ->with('pdf.financial-document-receipt', Mockery::type('array'))
        ->andReturn($pdfBuilder);

    (new SendFinancialDocumentJob($delivery->id))
        ->handle(app(FinancialDocumentViewDataService::class));

    $issuance->refresh();
    Storage::disk('private')->assertExists($issuance->pdf_path);
    Mail::assertSent(FinancialDocumentMail::class, fn (FinancialDocumentMail $mail): bool => $mail->hasTo('attached@example.test')
        && count($mail->attachments()) === 1);
    $mail = Mail::sent(FinancialDocumentMail::class)->sole();
    $plainText = view('emails.finance.financial-document-text', [
        'financialDocument' => $mail->document,
        'documentType' => $mail->type,
    ])->render();
    expect($issuance->status)->toBe(FinancialDocumentStatus::Sent)
        ->and($issuance->issued_by)->toBe($cashier->id)
        ->and($issuance->snapshot['cashier'])->toBe($cashier->name)
        ->and($issuance->pdf_checksum)->toBe(hash('sha256', '%PDF-1.4 official document'))
        ->and($mail->render())->toContain('Official eReceipt', 'OR-ATTACH-1')
        ->and($plainText)->toContain('Official eReceipt', 'We recorded your payment');

    $this->actingAs($cashier)
        ->get(portalUrlForAdministrators("/administrators/finance/documents/{$issuance->uuid}/download"))
        ->assertOk()
        ->assertDownload($issuance->attachmentFilename());

    Storage::disk('private')->put($issuance->pdf_path, '%PDF-1.4 tampered');
    $this->actingAs($cashier)
        ->get(portalUrlForAdministrators("/administrators/finance/documents/{$issuance->uuid}/download"))
        ->assertStatus(409);
});

it('regenerates the official PDF after correcting a receipt paper OR number', function (): void {
    Bus::fake();
    Mail::fake();
    Storage::fake('private');
    enableFinancialDocumentMail();
    $cashier = User::factory()->create(['role' => UserRole::Cashier]);
    $student = Student::factory()->create(['email' => 'corrected@example.test']);
    $transaction = Transaction::query()->create([
        'description' => 'Corrected paper OR test',
        'payment_method' => 'Cash',
        'status' => 'paid',
        'transaction_date' => now(),
        'settlements' => ['tuition_fee' => 900],
        'invoicenumber' => 'OR-ORIGINAL',
        'user_id' => $cashier->id,
    ]);
    StudentTransaction::query()->create([
        'student_id' => $student->id,
        'transaction_id' => $transaction->id,
        'amount' => 900,
        'status' => 'paid',
    ]);
    $issuance = FinancialDocumentIssuance::query()->with('deliveries')->sole();
    $firstDelivery = $issuance->deliveries->sole();
    $originalBuilder = Mockery::mock(PdfBuilder::class);
    $originalBuilder->shouldReceive('format')->once()->with(Format::A4)->andReturnSelf();
    $originalBuilder->shouldReceive('base64')->once()->andReturn(base64_encode('%PDF-1.4 original OR'));
    Pdf::shouldReceive('view')
        ->once()
        ->withArgs(fn (string $view, array $data): bool => $view === 'pdf.financial-document-receipt'
            && data_get($data, 'financialDocument.document.paper_reference') === 'OR-ORIGINAL')
        ->andReturn($originalBuilder);

    (new SendFinancialDocumentJob($firstDelivery->id))
        ->handle(app(FinancialDocumentViewDataService::class));

    $issuance->refresh();
    $stalePath = $issuance->pdf_path;
    Storage::disk('private')->assertExists($stalePath);

    app(FinancialDocumentService::class)->queueDelivery(
        $issuance,
        'corrected@example.test',
        'OR-CORRECTED',
    );

    $issuance->refresh();
    expect($issuance->paper_reference)->toBe('OR-CORRECTED')
        ->and($issuance->snapshot['reference_number'])->toBe('OR-CORRECTED')
        ->and($issuance->disk)->toBeNull()
        ->and($issuance->pdf_path)->toBeNull()
        ->and($issuance->pdf_checksum)->toBeNull();
    Storage::disk('private')->assertMissing($stalePath);

    $correctedBuilder = Mockery::mock(PdfBuilder::class);
    $correctedBuilder->shouldReceive('format')->once()->with(Format::A4)->andReturnSelf();
    $correctedBuilder->shouldReceive('base64')->once()->andReturn(base64_encode('%PDF-1.4 corrected OR'));
    Pdf::shouldReceive('view')
        ->once()
        ->withArgs(fn (string $view, array $data): bool => $view === 'pdf.financial-document-receipt'
            && data_get($data, 'financialDocument.document.paper_reference') === 'OR-CORRECTED')
        ->andReturn($correctedBuilder);
    $secondDelivery = $issuance->deliveries()->latest('id')->firstOrFail();

    (new SendFinancialDocumentJob($secondDelivery->id))
        ->handle(app(FinancialDocumentViewDataService::class));

    $issuance->refresh();
    Storage::disk('private')->assertExists($issuance->pdf_path);
    expect(Storage::disk('private')->get($issuance->pdf_path))->toBe('%PDF-1.4 corrected OR')
        ->and($issuance->pdf_checksum)->toBe(hash('sha256', '%PDF-1.4 corrected OR'));
});

it('records a sanitized permanent delivery failure without changing the payment', function (): void {
    Bus::fake();
    enableFinancialDocumentMail();
    $student = Student::factory()->create(['email' => 'failed@example.test']);
    $transaction = Transaction::query()->create([
        'description' => 'Still paid after mail failure',
        'payment_method' => 'Cash',
        'status' => 'paid',
        'transaction_date' => now(),
        'settlements' => ['others' => 800],
        'invoicenumber' => 'OR-FAIL-1',
    ]);
    StudentTransaction::query()->create([
        'student_id' => $student->id,
        'transaction_id' => $transaction->id,
        'amount' => 800,
        'status' => 'paid',
    ]);
    $issuance = FinancialDocumentIssuance::query()->with('deliveries')->sole();
    $delivery = $issuance->deliveries->sole();

    (new SendFinancialDocumentJob($delivery->id))
        ->failed(new RuntimeException('smtp-password=do-not-expose'));

    $issuance->refresh();
    $delivery->refresh();
    expect($transaction->fresh()->status)->toBe('paid')
        ->and($issuance->status)->toBe(FinancialDocumentStatus::Failed)
        ->and($issuance->failure_message)->not->toContain('smtp-password')
        ->and($delivery->error)->not->toContain('smtp-password')
        ->and($transaction->fresh()->receipt_email_status)->toBe('failed');
});

it('verifies authentic documents without exposing full student identity', function (): void {
    Bus::fake();
    enableFinancialDocumentMail();
    $student = Student::factory()->create([
        'first_name' => 'Alexandra',
        'last_name' => 'Santos',
        'student_id' => '2026-123456',
        'email' => 'verify@example.test',
    ]);
    $transaction = Transaction::query()->create([
        'description' => 'Verification test',
        'payment_method' => 'Cash',
        'status' => 'paid',
        'transaction_date' => now(),
        'settlements' => ['others' => 900],
        'invoicenumber' => 'OR-VERIFY-1',
    ]);
    StudentTransaction::query()->create([
        'student_id' => $student->id,
        'transaction_id' => $transaction->id,
        'amount' => 900,
        'status' => 'paid',
    ]);
    $issuance = FinancialDocumentIssuance::query()->sole();

    $this->get(portalUrlForAdministrators("/verify/finance/{$issuance->verification_token}"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('finance/verify-document', false)
            ->where('status', 'valid')
            ->where('document.student', fn (string $name): bool => $name !== $student->full_name)
            ->where('document.student_number', fn (string $number): bool => $number !== $student->student_id)
            ->missing('document.amount')
            ->missing('document.paper_reference')
        );

    $snapshot = $issuance->snapshot;
    $snapshot['amount'] = 9999;
    $issuance->update(['snapshot' => $snapshot]);

    $this->get(portalUrlForAdministrators("/verify/finance/{$issuance->verification_token}"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('status', 'integrity_failed'));
});

it('verifies historical documents with a retained previous application key', function (): void {
    $previousKey = 'base64:'.base64_encode(str_repeat('p', 32));
    $currentKey = 'base64:'.base64_encode(str_repeat('c', 32));
    config([
        'app.key' => $previousKey,
        'app.previous_keys' => [],
    ]);
    Bus::fake();
    enableFinancialDocumentMail();
    $student = Student::factory()->create(['email' => 'rotated-key@example.test']);
    $transaction = Transaction::query()->create([
        'description' => 'Pre-rotation payment',
        'payment_method' => 'Cash',
        'status' => 'paid',
        'transaction_date' => now(),
        'settlements' => ['tuition_fee' => 900],
        'invoicenumber' => 'OR-PRE-ROTATION',
    ]);
    StudentTransaction::query()->create([
        'student_id' => $student->id,
        'transaction_id' => $transaction->id,
        'amount' => 900,
        'status' => 'paid',
    ]);
    $issuance = FinancialDocumentIssuance::query()->sole();
    $verificationToken = $issuance->verification_token;

    config([
        'app.key' => $currentKey,
        'app.previous_keys' => [$previousKey],
    ]);

    expect(app(FinancialDocumentService::class)->hasValidIntegrity($issuance->fresh()))->toBeTrue();
    $this->get(portalUrlForAdministrators("/verify/finance/{$verificationToken}"))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('status', 'valid'));

    config(['app.previous_keys' => []]);
    expect(app(FinancialDocumentService::class)->hasValidIntegrity($issuance->fresh()))->toBeFalse();

    config(['app.previous_keys' => [$previousKey]]);
    $tamperedSnapshot = $issuance->snapshot;
    $tamperedSnapshot['amount'] = 9999;
    $issuance->update(['snapshot' => $tamperedSnapshot]);
    expect(app(FinancialDocumentService::class)->hasValidIntegrity($issuance->fresh()))->toBeFalse();
});
