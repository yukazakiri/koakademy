<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\AdminTransaction;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTransaction;
use App\Models\StudentTuition;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Modules\Inventory\Models\InventoryProduct;
use Modules\Inventory\Models\InventoryStockMovement;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function paymentWorkspaceCashier(): User
{
    $cashier = User::factory()->create(['role' => UserRole::Cashier]);
    Permission::findOrCreate('View:Cashier', 'web');

    $role = Role::findOrCreate($cashier->role->value, 'web');
    $role->syncPermissions(['View:Cashier']);
    $cashier->syncRoles([$role]);

    return $cashier;
}

/** @return array{enrollment: StudentEnrollment, tuition: StudentTuition} */
function paymentWorkspaceTuition(Student $student, float $balance = 100.00): array
{
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'academic_year' => 1,
    ]);
    $tuition = StudentTuition::query()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'academic_year' => 1,
        'total_tuition' => $balance,
        'total_lectures' => $balance,
        'total_laboratory' => 0,
        'total_miscelaneous_fees' => 0,
        'overall_tuition' => $balance,
        'total_balance' => $balance,
        'paid' => 0,
        'discount' => 0,
        'downpayment' => 0,
        'status' => 'pending',
    ]);

    return compact('enrollment', 'tuition');
}

/** @return array<string, mixed> */
function tuitionPaymentPayload(Student $student, StudentTuition $tuition, float $amount): array
{
    return [
        'student_id' => $student->id,
        'payment_method' => 'Cash',
        'reference_number' => 'OR-'.Str::upper(Str::random(10)),
        'remarks' => 'Tuition collection',
        'items' => [[
            'type' => 'tuition',
            'tuition_id' => $tuition->id,
            'amount' => $amount,
        ]],
    ];
}

it('shares the cashier-approved catalog charges with the payment workspace', function (): void {
    $cashier = paymentWorkspaceCashier();
    $uniform = InventoryProduct::factory()->create([
        'price' => 650.25,
        'is_active' => true,
    ]);

    $this->actingAs($cashier)
        ->get(portalUrlForAdministrators('/administrators/finance/payments/create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('fee_options', 9)
            ->where('fee_options.6.key', 'id_replacement')
            ->where('fee_options.6.label', 'ID Replacement')
            ->where('fee_options.7.key', 'lace_replacement')
            ->where('fee_options.7.label', 'Lace Replacement')
            ->where('items.0.id', $uniform->id)
            ->where('items.0.price', 650.25)
        );
});

it('records a cents-accurate tuition payment with enrollment and finance ledger links', function (): void {
    $cashier = paymentWorkspaceCashier();
    $student = Student::factory()->create();
    ['enrollment' => $enrollment, 'tuition' => $tuition] = paymentWorkspaceTuition($student);

    $this->actingAs($cashier)
        ->post(portalUrlForAdministrators('/administrators/finance/payments'), tuitionPaymentPayload($student, $tuition, 25.75))
        ->assertRedirect();

    $transaction = Transaction::query()->sole();
    $link = StudentTransaction::query()->sole();
    $tuition->refresh();

    expect((float) $link->amount)->toBe(25.75)
        ->and($link->student_enrollment_id)->toBe($enrollment->id)
        ->and((float) $tuition->paid)->toBe(25.75)
        ->and((float) $tuition->total_balance)->toBe(74.25)
        ->and((float) AdminTransaction::query()->where('transaction_id', $transaction->id)->sole()->amount)->toBe(25.75);
});

it('adds later cashier payments after an audited opening paid baseline', function (): void {
    $cashier = paymentWorkspaceCashier();
    $student = Student::factory()->create();
    ['tuition' => $tuition] = paymentWorkspaceTuition($student, 10000);

    $this->actingAs($cashier)
        ->post(portalUrlForAdministrators('/administrators/finance/payments'), tuitionPaymentPayload($student, $tuition, 3500))
        ->assertRedirect();

    $tuition->refresh()->forceFill([
        'paid' => 5000,
        'paid_transaction_baseline' => 3500,
    ])->save();

    $this->actingAs($cashier)
        ->post(portalUrlForAdministrators('/administrators/finance/payments'), tuitionPaymentPayload($student, $tuition, 500))
        ->assertRedirect();

    $tuition->refresh();
    expect((float) $tuition->getRawOriginal('paid'))->toBe(5000.0)
        ->and(app(App\Services\EnrollmentBillingService::class)->totalPaid($tuition))->toBe(5500.0)
        ->and((float) $tuition->total_balance)->toBe(4500.0);
});

it('forbids finance ledger endpoints for administrative users without cashier permission', function (): void {
    $administrator = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($administrator)
        ->postJson(portalUrlForAdministrators('/administrators/finance/payments/ledger/resolve'), [
            'student_identifiers' => ['208323'],
        ])
        ->assertForbidden();
});

it('rejects a tuition allocation that belongs to another student', function (): void {
    $cashier = paymentWorkspaceCashier();
    $student = Student::factory()->create();
    $otherStudent = Student::factory()->create();
    ['tuition' => $otherTuition] = paymentWorkspaceTuition($otherStudent);

    $this->actingAs($cashier)
        ->from(portalUrlForAdministrators('/administrators/finance/payments/create'))
        ->post(portalUrlForAdministrators('/administrators/finance/payments'), tuitionPaymentPayload($student, $otherTuition, 25.00))
        ->assertRedirect(portalUrlForAdministrators('/administrators/finance/payments/create'))
        ->assertSessionHasErrors('items.0.tuition_id');

    expect(Transaction::query()->count())->toBe(0)
        ->and(StudentTransaction::query()->count())->toBe(0)
        ->and(AdminTransaction::query()->count())->toBe(0);
});

it('rejects a tuition payment that exceeds the locked balance', function (): void {
    $cashier = paymentWorkspaceCashier();
    $student = Student::factory()->create();
    ['tuition' => $tuition] = paymentWorkspaceTuition($student, 50.00);

    $this->actingAs($cashier)
        ->from(portalUrlForAdministrators('/administrators/finance/payments/create'))
        ->post(portalUrlForAdministrators('/administrators/finance/payments'), tuitionPaymentPayload($student, $tuition, 50.01))
        ->assertRedirect(portalUrlForAdministrators('/administrators/finance/payments/create'))
        ->assertSessionHasErrors('items');

    expect(Transaction::query()->count())->toBe(0);
});

it('uses the live inventory price and records a locked stock deduction', function (): void {
    $cashier = paymentWorkspaceCashier();
    $student = Student::factory()->create();
    $product = InventoryProduct::factory()->create([
        'price' => 45.50,
        'stock_quantity' => 3,
        'track_stock' => true,
        'is_active' => true,
    ]);

    $this->actingAs($cashier)
        ->post(portalUrlForAdministrators('/administrators/finance/payments'), [
            'student_id' => $student->id,
            'payment_method' => 'GCash',
            'items' => [[
                'type' => 'item',
                'id' => $product->id,
                'amount' => 0.01,
                'quantity' => 2,
            ]],
        ])
        ->assertRedirect();

    $transaction = Transaction::query()->sole();
    $product->refresh();
    $movement = InventoryStockMovement::query()->sole();

    expect((float) $transaction->settlements['others'])->toBe(91.0)
        ->and($transaction->payment_method)->toBe('GCash')
        ->and($product->stock_quantity)->toBe(1)
        ->and($movement->quantity)->toBe(2)
        ->and($movement->previous_stock)->toBe(3)
        ->and($movement->new_stock)->toBe(1);
});

it('rejects inventory payments when the requested quantity is not in stock', function (): void {
    $cashier = paymentWorkspaceCashier();
    $student = Student::factory()->create();
    $product = InventoryProduct::factory()->create([
        'price' => 45.50,
        'stock_quantity' => 1,
        'track_stock' => true,
        'is_active' => true,
    ]);

    $this->actingAs($cashier)
        ->from(portalUrlForAdministrators('/administrators/finance/payments/create'))
        ->post(portalUrlForAdministrators('/administrators/finance/payments'), [
            'student_id' => $student->id,
            'payment_method' => 'Cash',
            'items' => [[
                'type' => 'item',
                'id' => $product->id,
                'quantity' => 2,
            ]],
        ])
        ->assertRedirect(portalUrlForAdministrators('/administrators/finance/payments/create'))
        ->assertSessionHasErrors('items');

    expect(Transaction::query()->count())->toBe(0)
        ->and($product->fresh()->stock_quantity)->toBe(1);
});

it('resolves pasted student identifiers with their open tuition allocations', function (): void {
    $cashier = paymentWorkspaceCashier();
    $student = Student::factory()->create(['student_id' => 208323]);
    ['tuition' => $tuition] = paymentWorkspaceTuition($student, 800.00);

    $this->actingAs($cashier)
        ->postJson(portalUrlForAdministrators('/administrators/finance/payments/ledger/resolve'), [
            'student_identifiers' => ['208323', 'UNKNOWN'],
        ])
        ->assertOk()
        ->assertJsonPath('matches.0.identifier', '208323')
        ->assertJsonPath('matches.0.students.0.id', $student->id)
        ->assertJsonPath('matches.0.students.0.open_tuitions.0.id', $tuition->id)
        ->assertJsonPath('matches.0.students.0.open_tuitions.0.balance', 800)
        ->assertJsonPath('matches.1.students', []);
});

it('resolves a typed student ID even when the student only needs a catalog charge', function (): void {
    $cashier = paymentWorkspaceCashier();
    $student = Student::factory()->create(['student_id' => 208324]);

    $this->actingAs($cashier)
        ->postJson(portalUrlForAdministrators('/administrators/finance/payments/ledger/resolve'), [
            'student_identifiers' => [' 208324 ', '208324'],
        ])
        ->assertOk()
        ->assertJsonCount(1, 'matches')
        ->assertJsonPath('matches.0.identifier', '208324')
        ->assertJsonPath('matches.0.students.0.id', $student->id)
        ->assertJsonPath('matches.0.students.0.open_tuitions', []);
});

it('records fee and inventory spreadsheet rows for a student without tuition', function (): void {
    $cashier = paymentWorkspaceCashier();
    $student = Student::factory()->create();
    $uniform = InventoryProduct::factory()->create([
        'name' => 'School Uniform',
        'price' => 650.00,
        'stock_quantity' => 2,
        'track_stock' => true,
        'is_active' => true,
    ]);

    $this->actingAs($cashier)
        ->postJson(portalUrlForAdministrators('/administrators/finance/payments/batch'), [
            'batch_id' => (string) Str::uuid(),
            'rows' => [
                [
                    'client_row_id' => 'id-replacement',
                    'student_id' => $student->id,
                    'payment_method' => 'Cash',
                    'items' => [[
                        'type' => 'fee',
                        'fee_key' => 'id_replacement',
                        'amount' => 75.50,
                    ]],
                ],
                [
                    'client_row_id' => 'lace-replacement',
                    'student_id' => $student->id,
                    'payment_method' => 'Maya',
                    'items' => [[
                        'type' => 'fee',
                        'fee_key' => 'lace_replacement',
                        'amount' => 30.00,
                    ]],
                ],
                [
                    'client_row_id' => 'uniform',
                    'student_id' => $student->id,
                    'payment_method' => 'GCash',
                    'items' => [[
                        'type' => 'item',
                        'id' => $uniform->id,
                        'quantity' => 1,
                    ]],
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('results.0.status', 'recorded')
        ->assertJsonPath('results.0.amount', 75.5)
        ->assertJsonPath('results.1.status', 'recorded')
        ->assertJsonPath('results.1.amount', 30)
        ->assertJsonPath('results.2.status', 'recorded')
        ->assertJsonPath('results.2.amount', 650)
        ->assertJsonPath('summary.recorded_count', 3);

    $transactions = Transaction::query()->orderBy('id')->get();
    $uniform->refresh();

    expect($transactions)->toHaveCount(3)
        ->and((float) $transactions[0]->settlements['id_replacement'])->toBe(75.50)
        ->and((float) $transactions[1]->settlements['lace_replacement'])->toBe(30.00)
        ->and((float) $transactions[2]->settlements['others'])->toBe(650.00)
        ->and(StudentTransaction::query()->whereNull('student_enrollment_id')->count())->toBe(3)
        ->and(AdminTransaction::query()->count())->toBe(3)
        ->and($uniform->stock_quantity)->toBe(1)
        ->and(InventoryStockMovement::query()->where('product_id', $uniform->id)->count())->toBe(1);
});

it('records valid spreadsheet rows, retains rejected rows, and makes retries idempotent', function (): void {
    $cashier = paymentWorkspaceCashier();
    $student = Student::factory()->create();
    ['tuition' => $tuition] = paymentWorkspaceTuition($student, 40.00);
    $batchId = (string) Str::uuid();
    $rows = [
        [
            ...tuitionPaymentPayload($student, $tuition, 10.25),
            'client_row_id' => 'row-1',
        ],
        [
            ...tuitionPaymentPayload($student, $tuition, 50.00),
            'client_row_id' => 'row-2',
        ],
    ];

    $first = $this->actingAs($cashier)
        ->postJson(portalUrlForAdministrators('/administrators/finance/payments/batch'), [
            'batch_id' => $batchId,
            'rows' => $rows,
        ]);

    $first->assertOk()
        ->assertJsonPath('results.0.client_row_id', 'row-1')
        ->assertJsonPath('results.0.status', 'recorded')
        ->assertJsonPath('results.0.amount', 10.25)
        ->assertJsonPath('results.1.client_row_id', 'row-2')
        ->assertJsonPath('results.1.status', 'rejected')
        ->assertJsonPath('summary.recorded_count', 1)
        ->assertJsonPath('summary.rejected_count', 1);

    $this->actingAs($cashier)
        ->postJson(portalUrlForAdministrators('/administrators/finance/payments/batch'), [
            'batch_id' => $batchId,
            'rows' => [$rows[0]],
        ])
        ->assertOk()
        ->assertJsonPath('results.0.status', 'duplicate')
        ->assertJsonPath('results.0.amount', 10.25)
        ->assertJsonPath('summary.duplicate_count', 1);

    $tuition->refresh();
    expect(Transaction::query()->count())->toBe(1)
        ->and(StudentTransaction::query()->count())->toBe(1)
        ->and(AdminTransaction::query()->count())->toBe(1)
        ->and((float) $tuition->paid)->toBe(10.25)
        ->and((float) $tuition->total_balance)->toBe(29.75);
});
