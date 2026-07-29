<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Jobs\SendTransactionReceiptJob;
use App\Mail\TransactionReceiptMail;
use App\Models\Student;
use App\Models\StudentTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionReceiptDataService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function grantFinancePermission(User $user): void
{
    Permission::findOrCreate('View:Cashier', 'web');

    $role = Role::findOrCreate($user->role->value, 'web');
    $role->syncPermissions(['View:Cashier']);
}

it('redirects guests away from finance reports page', function (): void {
    $this->get(portalUrlForAdministrators('/administrators/finance/reports'))
        ->assertRedirect('/login');
});

it('forbids non-administrative users from accessing finance reports', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Instructor,
        'faculty_id_number' => 'FAC-101',
    ]);

    $this->actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/finance/reports'))
        ->assertForbidden();
});

it('allows administrative users to view the finance reports page', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantFinancePermission($user);

    $this->actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/finance/reports'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/finance/reports', false)
            ->has('filters')
            ->has('filters.school_years')
            ->has('filters.semesters')
            ->has('filters.payment_methods')
            ->has('filters.current_school_year')
            ->has('filters.current_semester')
        );
});

it('allows cashier users to view the finance reports page', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Cashier,
    ]);

    grantFinancePermission($user);

    $this->actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/finance/reports'))
        ->assertOk();
});

it('shares cashier desk data on the finance dashboard', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantFinancePermission($user);

    $this->actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/finance'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/finance/dashboard', false)
            ->has('stats.total_revenue')
            ->has('stats.total_collectibles')
            ->has('cashier_desk.ready_for_collection')
            ->has('cashier_desk.average_transaction_today')
            ->has('cashier_desk.next_actions')
            ->has('collection_queue')
            ->has('recent_transactions')
        );
});

it('shares payment desk summaries and active filters', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantFinancePermission($user);

    $this->actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/finance/payments?search=receipt&method=Cash&status=paid'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/finance/payments', false)
            ->has('payments.data')
            ->has('summary.total_transactions')
            ->has('summary.total_collected')
            ->has('summary.today_transactions')
            ->has('summary.today_collected')
            ->has('summary.payment_methods')
            ->where('filters.search', 'receipt')
            ->where('filters.method', 'Cash')
            ->where('filters.status', 'paid')
        );
});

it('shares the cashier payment entry contract', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Cashier,
    ]);

    grantFinancePermission($user);

    $this->actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/finance/payments/create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/finance/create-payment', false)
            ->has('user.name')
            ->has('user.email')
            ->has('user.role')
            ->has('items')
            ->has('currency')
        );
});

it('allows cashiers to search students for payment entry by name and school ID', function (): void {
    $cashier = User::factory()->create([
        'role' => UserRole::Cashier,
    ]);
    $student = Student::factory()->create([
        'first_name' => 'Rizalina',
        'last_name' => 'Mercado',
        'student_id' => 208323,
    ]);

    grantFinancePermission($cashier);

    $nameResponse = $this->actingAs($cashier)
        ->getJson(portalUrlForAdministrators('/administrators/enrollments/api/students?search=Rizalina%20Mercado'));

    $nameResponse
        ->assertOk()
        ->assertJsonFragment([
            'id' => $student->id,
            'student_id' => 208323,
            'full_name' => $student->full_name,
        ]);

    $studentIdResponse = $this->actingAs($cashier)
        ->getJson(portalUrlForAdministrators('/administrators/enrollments/api/students?search=208323'));

    $studentIdResponse
        ->assertOk()
        ->assertJsonFragment([
            'id' => $student->id,
            'student_id' => 208323,
            'full_name' => $student->full_name,
        ]);
});

it('returns a complete student transaction ledger for cashiers', function (): void {
    $cashier = User::factory()->create([
        'role' => UserRole::Cashier,
    ]);
    $student = Student::factory()->create();

    grantFinancePermission($cashier);

    $transaction = Transaction::query()->create([
        'description' => 'Partial tuition payment',
        'payment_method' => 'Cash',
        'status' => 'paid',
        'transaction_date' => now(),
        'settlements' => [
            'tuition_fee' => 2500,
            'others' => 0,
        ],
        'invoicenumber' => 'OR-2026-001',
        'user_id' => $cashier->id,
    ]);

    StudentTransaction::query()->create([
        'student_id' => $student->id,
        'transaction_id' => $transaction->id,
        'amount' => 2500,
        'status' => 'paid',
    ]);

    $this->actingAs($cashier)
        ->getJson(portalUrlForAdministrators("/administrators/finance/api/students/{$student->id}/transactions"))
        ->assertOk()
        ->assertJsonPath('summary.count', 1)
        ->assertJsonPath('summary.total_paid', 2500)
        ->assertJsonPath('transactions.0.reference_number', 'OR-2026-001')
        ->assertJsonPath('transactions.0.payment_method', 'Cash')
        ->assertJsonPath('transactions.0.cashier', $cashier->name)
        ->assertJsonPath('transactions.0.remarks', 'Partial tuition payment')
        ->assertJsonPath('transactions.0.settlements.tuition_fee', 2500)
        ->assertJsonMissingPath('transactions.0.settlements.others')
        ->assertJsonStructure([
            'transactions' => [[
                'id',
                'transaction_number',
                'reference_number',
                'date',
                'time',
                'amount',
                'payment_method',
                'status',
                'cashier',
                'remarks',
                'settlements',
                'receipt_url',
            ]],
            'summary' => ['count', 'total_paid'],
        ]);
});

it('protects student transaction history from users without cashier access', function (): void {
    $student = Student::factory()->create();
    $instructor = User::factory()->create([
        'role' => UserRole::Instructor,
        'faculty_id_number' => 'FAC-LEDGER-101',
    ]);

    $this->actingAs($instructor)
        ->getJson(portalUrlForAdministrators("/administrators/finance/api/students/{$student->id}/transactions"))
        ->assertForbidden();
});

it('queues an e-receipt after recording a payment', function (): void {
    Bus::fake();

    $cashier = User::factory()->create(['role' => UserRole::Cashier]);
    $student = Student::factory()->create(['email' => 'payer@example.com']);
    grantFinancePermission($cashier);

    $this->actingAs($cashier)
        ->post(portalUrlForAdministrators('/administrators/finance/payments'), [
            'student_id' => $student->id,
            'payment_method' => 'Cash',
            'reference_number' => 'OR-AUTO-001',
            'remarks' => 'Registration payment',
            'items' => [[
                'type' => 'fee',
                'name' => 'Registration Fee',
                'amount' => 1500,
                'fee_key' => 'registration_fee',
            ]],
        ])
        ->assertRedirect();

    $transaction = Transaction::query()->latest('id')->firstOrFail();

    expect($transaction->receipt_email_status)->toBe('pending')
        ->and($transaction->receipt_email_recipient)->toBe('payer@example.com')
        ->and($transaction->receipt_email_delivery_id)->not->toBeNull();

    Bus::assertDispatched(SendTransactionReceiptJob::class, fn (SendTransactionReceiptJob $job): bool => $job->transactionId === $transaction->id
        && $job->recipient === 'payer@example.com'
        && $job->deliveryId === $transaction->receipt_email_delivery_id);
});

it('records a payment without queueing email when the student has no address', function (): void {
    Bus::fake();

    $cashier = User::factory()->create(['role' => UserRole::Cashier]);
    $student = Student::factory()->create(['email' => null]);
    grantFinancePermission($cashier);

    $this->actingAs($cashier)
        ->post(portalUrlForAdministrators('/administrators/finance/payments'), [
            'student_id' => $student->id,
            'payment_method' => 'Cash',
            'items' => [[
                'type' => 'fee',
                'name' => 'Certification',
                'amount' => 300,
                'fee_key' => 'certification',
            ]],
        ])
        ->assertRedirect();

    expect(Transaction::query()->latest('id')->firstOrFail()->receipt_email_status)->toBe('skipped');
    Bus::assertNotDispatched(SendTransactionReceiptJob::class);
});

it('allows cashiers to resend a receipt to an override address', function (): void {
    Bus::fake();

    $cashier = User::factory()->create(['role' => UserRole::Cashier]);
    grantFinancePermission($cashier);
    $transaction = Transaction::query()->create([
        'description' => 'Payment',
        'payment_method' => 'Cash',
        'status' => 'paid',
        'transaction_date' => now(),
        'settlements' => ['others' => 500],
        'user_id' => $cashier->id,
        'receipt_email_status' => 'failed',
    ]);

    $this->actingAs($cashier)
        ->post(portalUrlForAdministrators("/administrators/finance/payments/{$transaction->id}/resend-receipt"), [
            'recipient' => 'corrected@example.com',
        ])
        ->assertRedirect();

    $transaction->refresh();
    expect($transaction->receipt_email_status)->toBe('pending')
        ->and($transaction->receipt_email_recipient)->toBe('corrected@example.com');

    Bus::assertDispatched(SendTransactionReceiptJob::class, fn (SendTransactionReceiptJob $job): bool => $job->deliveryId === $transaction->receipt_email_delivery_id);
});

it('prevents duplicate receipt delivery while one is pending', function (): void {
    Bus::fake();

    $cashier = User::factory()->create(['role' => UserRole::Cashier]);
    grantFinancePermission($cashier);
    $transaction = Transaction::query()->create([
        'description' => 'Payment',
        'payment_method' => 'Cash',
        'status' => 'paid',
        'transaction_date' => now(),
        'settlements' => ['others' => 500],
        'user_id' => $cashier->id,
        'receipt_email_status' => 'pending',
        'receipt_email_delivery_id' => fake()->uuid(),
        'receipt_email_recipient' => 'pending@example.com',
    ]);

    $this->actingAs($cashier)
        ->post(portalUrlForAdministrators("/administrators/finance/payments/{$transaction->id}/resend-receipt"), [
            'recipient' => 'duplicate@example.com',
        ])
        ->assertRedirect();

    expect($transaction->fresh()->receipt_email_recipient)->toBe('pending@example.com');
    Bus::assertNotDispatched(SendTransactionReceiptJob::class);
});

it('shares document and email delivery data on the receipt page', function (): void {
    $cashier = User::factory()->create(['role' => UserRole::Cashier]);
    $student = Student::factory()->create(['email' => 'receipt@example.com']);
    grantFinancePermission($cashier);
    $transaction = Transaction::query()->create([
        'description' => 'Tuition payment',
        'payment_method' => 'Cash',
        'status' => 'paid',
        'transaction_date' => now(),
        'settlements' => ['tuition_fee' => 2000],
        'user_id' => $cashier->id,
        'receipt_email_status' => 'sent',
        'receipt_email_recipient' => 'receipt@example.com',
        'receipt_emailed_at' => now(),
    ]);
    StudentTransaction::query()->create([
        'student_id' => $student->id,
        'transaction_id' => $transaction->id,
        'amount' => 2000,
        'status' => 'paid',
    ]);

    $this->actingAs($cashier)
        ->get(portalUrlForAdministrators("/administrators/finance/payments/{$transaction->id}"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/finance/receipt', false)
            ->where('transaction.student_email', 'receipt@example.com')
            ->where('transaction.items.tuition_fee', 2000)
            ->where('transaction.email_delivery.status', 'sent')
            ->where('transaction.email_delivery.recipient', 'receipt@example.com')
            ->has('transaction.institution.name')
        );
});

it('emails the document receipt with a PDF attachment', function (): void {
    Mail::fake();

    $pdfBuilder = Mockery::mock(PdfBuilder::class);
    $pdfBuilder->shouldReceive('format')
        ->once()
        ->with(Format::A4)
        ->andReturnSelf();
    $pdfBuilder->shouldReceive('base64')
        ->once()
        ->andReturn(base64_encode('%PDF-1.4 test receipt'));

    Pdf::shouldReceive('view')
        ->once()
        ->with('pdf.transaction-receipt', Mockery::on(
            fn (array $data): bool => isset($data['receipt']) && is_array($data['receipt'])
        ))
        ->andReturn($pdfBuilder);

    $cashier = User::factory()->create(['role' => UserRole::Cashier]);
    $student = Student::factory()->create(['email' => 'attached@example.com']);
    $deliveryId = fake()->uuid();
    $transaction = Transaction::query()->create([
        'description' => 'Document receipt test',
        'payment_method' => 'Cash',
        'status' => 'paid',
        'transaction_date' => now(),
        'settlements' => ['tuition_fee' => 1250],
        'user_id' => $cashier->id,
        'receipt_email_status' => 'pending',
        'receipt_email_delivery_id' => $deliveryId,
        'receipt_email_recipient' => 'attached@example.com',
    ]);
    StudentTransaction::query()->create([
        'student_id' => $student->id,
        'transaction_id' => $transaction->id,
        'amount' => 1250,
        'status' => 'paid',
    ]);

    (new SendTransactionReceiptJob($transaction->id, 'attached@example.com', $deliveryId))
        ->handle(app(TransactionReceiptDataService::class));

    Mail::assertSent(TransactionReceiptMail::class, function (TransactionReceiptMail $mail): bool {
        return $mail->hasTo('attached@example.com')
            && $mail->receipt['amount'] === 1250.0
            && count($mail->attachments()) === 1;
    });
    expect($transaction->fresh()->receipt_email_status)->toBe('sent')
        ->and($transaction->fresh()->receipt_emailed_at)->not->toBeNull();
});

it('shares billing desk summaries and active filters', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantFinancePermission($user);

    $this->actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/finance/invoices?search=student&status=unpaid'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/finance/invoices', false)
            ->has('invoices.data')
            ->has('summary.total_billings')
            ->has('summary.total_assessed')
            ->has('summary.total_outstanding')
            ->has('summary.paid_count')
            ->has('summary.unpaid_count')
            ->where('filters.search', 'student')
            ->where('filters.status', 'unpaid')
        );
});

it('forbids registrar users from accessing finance reports without cashier permission', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Registrar,
    ]);

    $this->actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/finance/reports'))
        ->assertForbidden();
});

it('returns daily collection report data', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantFinancePermission($user);

    $this->actingAs($user)
        ->getJson(portalUrlForAdministrators('/administrators/finance/reports/daily-collection?date='.now()->format('Y-m-d')))
        ->assertOk()
        ->assertJsonStructure([
            'transactions',
            'summary' => [
                'total_transactions',
                'total_amount',
                'by_payment_method',
                'date',
            ],
        ]);
});

it('returns collection report data for date range', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantFinancePermission($user);

    $this->actingAs($user)
        ->getJson(portalUrlForAdministrators('/administrators/finance/reports/collection?start_date='.now()->subDays(30)->format('Y-m-d').'&end_date='.now()->format('Y-m-d')))
        ->assertOk()
        ->assertJsonStructure([
            'transactions',
            'summary' => [
                'total_transactions',
                'total_amount',
                'by_payment_method',
                'daily_breakdown',
                'start_date',
                'end_date',
            ],
        ]);
});

it('returns outstanding balances report data', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantFinancePermission($user);

    $this->actingAs($user)
        ->getJson(portalUrlForAdministrators('/administrators/finance/reports/outstanding-balances'))
        ->assertOk()
        ->assertJsonStructure([
            'students',
            'summary' => [
                'total_students',
                'total_outstanding',
                'total_collectible',
                'total_collected',
                'collection_rate',
                'school_year',
                'semester',
            ],
        ]);
});

it('returns scholarship report data', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantFinancePermission($user);

    $this->actingAs($user)
        ->getJson(portalUrlForAdministrators('/administrators/finance/reports/scholarship'))
        ->assertOk()
        ->assertJsonStructure([
            'scholars',
            'summary' => [
                'total_scholars',
                'total_discount_granted',
                'original_revenue',
                'discounted_revenue',
                'by_discount_level',
                'school_year',
                'semester',
            ],
        ]);
});

it('returns revenue breakdown report data', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantFinancePermission($user);

    $this->actingAs($user)
        ->getJson(portalUrlForAdministrators('/administrators/finance/reports/revenue-breakdown'))
        ->assertOk()
        ->assertJsonStructure([
            'summary' => [
                'total_revenue',
                'total_transactions',
                'breakdown',
                'monthly_trend',
                'school_year',
                'semester',
            ],
        ]);
});

it('returns fully paid students report data', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantFinancePermission($user);

    $this->actingAs($user)
        ->getJson(portalUrlForAdministrators('/administrators/finance/reports/fully-paid'))
        ->assertOk()
        ->assertJsonStructure([
            'students',
            'summary' => [
                'total_students',
                'total_collected',
                'school_year',
                'semester',
            ],
        ]);
});

it('returns cashier performance report data', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantFinancePermission($user);

    $this->actingAs($user)
        ->getJson(portalUrlForAdministrators('/administrators/finance/reports/cashier-performance?start_date='.now()->subDays(30)->format('Y-m-d').'&end_date='.now()->format('Y-m-d')))
        ->assertOk()
        ->assertJsonStructure([
            'cashiers',
            'summary' => [
                'total_cashiers',
                'total_transactions',
                'total_collected',
                'start_date',
                'end_date',
            ],
        ]);
});

it('validates required date fields for collection report', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantFinancePermission($user);

    $this->actingAs($user)
        ->getJson(portalUrlForAdministrators('/administrators/finance/reports/collection'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['start_date', 'end_date']);
});

it('validates required date fields for cashier performance report', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantFinancePermission($user);

    $this->actingAs($user)
        ->getJson(portalUrlForAdministrators('/administrators/finance/reports/cashier-performance'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['start_date', 'end_date']);
});
