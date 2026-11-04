<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

use App\Enums\UserRole;
use App\Features\Toggles\StudentTuition as StudentTuitionFeature;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTransaction;
use App\Models\StudentTuition;
use App\Models\StudentTuitionUpdateRequest;
use App\Models\Transaction;
use App\Models\TuitionAdjustment;
use App\Models\TuitionAdjustmentBatch;
use App\Models\User;
use App\Notifications\StudentTuitionUpdateRequestReviewedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Laravel\Pennant\Feature;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    $this->withoutVite();
    Feature::activateForEveryone(StudentTuitionFeature::class);
    config(['inertia.testing.ensure_pages_exist' => false]);
});

/** @return array{0: User, 1: Student, 2: StudentEnrollment, 3: StudentTuition} */
function tuitionRequestStudent(): array
{
    $user = User::factory()->create(['role' => UserRole::Student]);
    $course = Course::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id, 'email' => $user->email, 'course_id' => $course->id]);
    $enrollment = StudentEnrollment::factory()->create(['student_id' => $student->id, 'course_id' => $course->id, 'school_year' => '2026 - 2027', 'semester' => 1, 'academic_year' => 1]);
    $tuition = StudentTuition::query()->create([
        'student_id' => $student->id, 'enrollment_id' => $enrollment->id, 'school_year' => '2026 - 2027', 'semester' => 1, 'academic_year' => 1,
        'total_tuition' => 10000, 'total_lectures' => 10000, 'total_laboratory' => 0, 'total_miscelaneous_fees' => 0,
        'overall_tuition' => 10000, 'total_balance' => 10000, 'paid' => 0, 'discount' => 0, 'downpayment' => 0, 'status' => 'pending',
    ]);

    return [$user, $student, $enrollment, $tuition];
}

function tuitionRequestFinanceUser(): User
{
    $user = User::factory()->create(['role' => UserRole::AccountingOfficer]);
    foreach (['view_tuition_fees', 'manage_tuition_fees'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $user->givePermissionTo(['view_tuition_fees', 'manage_tuition_fees']);

    return $user;
}

it('lets a student submit and view their own tuition update request', function (): void {
    [$user, $student] = tuitionRequestStudent();

    $this->actingAs($user)
        ->post(route('student.tuition.update-requests.store'), [
            'school_year' => '2026 - 2027', 'semester' => 1, 'concern_type' => 'missing_payment',
            'receipt_number' => 'OR-2026-001', 'details' => 'I paid my downpayment yesterday, but it is still not reflected.',
        ])->assertRedirect();

    $request = StudentTuitionUpdateRequest::query()->sole();
    expect($request->student_id)->toBe($student->id)
        ->and($request->student_enrollment_id)->not->toBeNull()
        ->and($request->status)->toBe(StudentTuitionUpdateRequest::StatusPending)
        ->and($request->events()->count())->toBe(1);

    $this->actingAs($user)
        ->get(route('student.tuition.update-requests.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('student/tuition/update-requests')
            ->has('requests', 1)
            ->where('requests.0.receipt_number', 'OR-2026-001')
        );
});

it('requires a receipt number only for payment-not-reflected requests and prevents duplicate active requests', function (): void {
    [$user] = tuitionRequestStudent();
    $payload = ['school_year' => '2026 - 2027', 'semester' => 1, 'concern_type' => 'missing_payment', 'details' => 'A verified payment remains missing from my tuition page.'];

    $this->actingAs($user)->post(route('student.tuition.update-requests.store'), $payload)->assertSessionHasErrors('receipt_number');

    $payload['receipt_number'] = 'OR-2026-002';
    $this->actingAs($user)->post(route('student.tuition.update-requests.store'), $payload)->assertRedirect();
    $this->actingAs($user)->post(route('student.tuition.update-requests.store'), $payload)->assertSessionHasErrors('concern_type');

    $this->actingAs($user)->post(route('student.tuition.update-requests.store'), [
        'school_year' => '2026 - 2027', 'semester' => 1, 'concern_type' => 'discount',
        'details' => 'My approved scholarship discount is not displayed in the assessment.',
    ])->assertRedirect();
});

it('falls back to an available academic period for an invalid selection', function (): void {
    [$user] = tuitionRequestStudent();

    $this->actingAs($user)->get(route('student.tuition.update-requests.index', [
        'school_year' => '1999 - 2000', 'semester' => 9,
    ]))->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('periods', 1)
        ->where('error', null)
        ->where('selected_period.school_year', '2026 - 2027')
        ->where('selected_period.semester', 1)
    );
});

it('preserves a valid older academic period and confirms submission for that period', function (): void {
    [$user, $student, $enrollment] = tuitionRequestStudent();
    StudentEnrollment::factory()->create([
        'student_id' => $student->id, 'course_id' => $enrollment->course_id,
        'school_year' => '2025 - 2026', 'semester' => 2, 'academic_year' => 1,
    ]);
    $url = route('student.tuition.update-requests.index', ['school_year' => '2025 - 2026', 'semester' => 2]);

    $this->actingAs($user)->get($url)->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('periods', 2)
        ->where('selected_period.school_year', '2025 - 2026')->where('selected_period.semester', 2));

    $response = $this->actingAs($user)->post(route('student.tuition.update-requests.store'), [
        'school_year' => '2025 - 2026', 'semester' => 2, 'concern_type' => 'other',
        'details' => 'Please check the assessment for my previous academic period.',
    ])->assertRedirect($url)->assertSessionHasNoErrors();
    $request = StudentTuitionUpdateRequest::query()->sole();
    $response->assertSessionHas('tuition_request_id', $request->id);
    expect($request->school_year)->toBe('2025 - 2026')->and($request->semester)->toBe(2);
    $this->get($url)->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('submitted_request_id', $request->id)->where('requests.0.id', $request->id));
});

it('discards receipt data for nonpayment concerns', function (string $concern): void {
    [$user] = tuitionRequestStudent();
    $this->actingAs($user)->post(route('student.tuition.update-requests.store'), [
        'school_year' => '2026 - 2027', 'semester' => 1, 'concern_type' => $concern,
        'receipt_number' => 'STALE-RECEIPT', 'details' => 'Please check the incorrect amount on my assessment.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(StudentTuitionUpdateRequest::query()->sole()->receipt_number)->toBeNull();
})->with(['discount', 'subject_change', 'other']);

it('returns an empty period list for a student without academic records', function (): void {
    $user = User::factory()->create(['role' => UserRole::Student]);
    Student::factory()->create(['user_id' => $user->id, 'email' => $user->email]);

    $this->actingAs($user)->get(route('student.tuition.update-requests.index'))
        ->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->has('periods', 0)->has('requests', 0)->where('error', null));
});

it('rejects a submission for an academic period without student records', function (): void {
    [$user] = tuitionRequestStudent();
    $this->actingAs($user)->post(route('student.tuition.update-requests.store'), [
        'school_year' => '1999 - 2000', 'semester' => 1, 'concern_type' => 'other',
        'details' => 'Please check the amount on my tuition assessment.',
    ])->assertSessionHasErrors('school_year');

    expect(StudentTuitionUpdateRequest::query()->count())->toBe(0);
});

it('does not let a student see another students tuition update request', function (): void {
    [$owner] = tuitionRequestStudent();
    [$other] = tuitionRequestStudent();
    $this->actingAs($owner)->post(route('student.tuition.update-requests.store'), [
        'school_year' => '2026 - 2027', 'semester' => 1, 'concern_type' => 'other', 'details' => 'Please review the amount shown on my tuition assessment.',
    ])->assertRedirect();

    $this->actingAs($other)->get(route('student.tuition.update-requests.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('requests', 0));
});

it('lets Finance claim and resolve a request only with the matching verified payment', function (): void {
    Notification::fake();
    [$studentUser, $student, $enrollment] = tuitionRequestStudent();
    $finance = tuitionRequestFinanceUser();
    $this->actingAs($studentUser)->post(route('student.tuition.update-requests.store'), [
        'school_year' => '2026 - 2027', 'semester' => 1, 'concern_type' => 'missing_payment',
        'receipt_number' => 'OR-2026-003', 'details' => 'My cashier payment has not yet appeared on the tuition portal.',
    ]);
    $request = StudentTuitionUpdateRequest::query()->sole();
    $transaction = Transaction::query()->create([
        'description' => 'Tuition payment', 'status' => 'paid', 'transaction_date' => now(),
        'settlements' => ['tuition_fee' => 1000], 'invoicenumber' => 'OR-2026-003',
    ]);
    StudentTransaction::query()->create([
        'student_id' => $student->id, 'student_enrollment_id' => $enrollment->id, 'transaction_id' => $transaction->id, 'amount' => 1000, 'status' => 'paid',
    ]);

    $this->actingAs($finance)->get(portalUrlForAdministrators('/administrators/finance/tuition-update-requests'))
        ->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('administrators/finance/tuition-update-requests/index')->has('requests.data', 1));
    $this->actingAs($finance)->post(route('administrators.finance.tuition-update-requests.claim', $request))->assertRedirect();
    $this->actingAs($finance)->post(route('administrators.finance.tuition-update-requests.resolve-payment', $request), [
        'transaction_id' => $transaction->id, 'resolution_note' => 'Your cashier payment was verified and is now reflected in your tuition record.',
    ])->assertRedirect();

    $request->refresh();
    expect($request->status)->toBe(StudentTuitionUpdateRequest::StatusResolved)
        ->and($request->resolved_transaction_id)->toBe($transaction->id)
        ->and($request->open_key)->toBeNull()
        ->and($request->events()->count())->toBe(3);
    Notification::assertSentTo($studentUser, StudentTuitionUpdateRequestReviewedNotification::class);
});

it('rejects a transaction from another student and keeps the request in review', function (): void {
    [$studentUser] = tuitionRequestStudent();
    [, $otherStudent, $otherEnrollment] = tuitionRequestStudent();
    $finance = tuitionRequestFinanceUser();
    $this->actingAs($studentUser)->post(route('student.tuition.update-requests.store'), [
        'school_year' => '2026 - 2027', 'semester' => 1, 'concern_type' => 'missing_payment',
        'receipt_number' => 'OR-2026-004', 'details' => 'My payment must be reconciled with the cashier ledger.',
    ]);
    $request = StudentTuitionUpdateRequest::query()->sole();
    $transaction = Transaction::query()->create(['description' => 'Other payment', 'status' => 'paid', 'transaction_date' => now(), 'settlements' => ['tuition_fee' => 1000], 'invoicenumber' => 'OR-2026-004']);
    StudentTransaction::query()->create(['student_id' => $otherStudent->id, 'student_enrollment_id' => $otherEnrollment->id, 'transaction_id' => $transaction->id, 'amount' => 1000, 'status' => 'paid']);

    $this->actingAs($finance)->post(route('administrators.finance.tuition-update-requests.claim', $request));
    $this->actingAs($finance)->from(route('administrators.finance.tuition-update-requests.show', $request))
        ->post(route('administrators.finance.tuition-update-requests.resolve-payment', $request), [
            'transaction_id' => $transaction->id, 'resolution_note' => 'This must not resolve.',
        ])->assertSessionHasErrors('transaction_id');

    expect($request->refresh()->status)->toBe(StudentTuitionUpdateRequest::StatusInReview);
});

it('links a reviewed assessment request only to its matching tuition adjustment audit', function (): void {
    Notification::fake();
    [$studentUser, $student, $enrollment, $tuition] = tuitionRequestStudent();
    $finance = tuitionRequestFinanceUser();
    $this->actingAs($studentUser)->post(route('student.tuition.update-requests.store'), [
        'school_year' => '2026 - 2027', 'semester' => 1, 'concern_type' => 'discount',
        'details' => 'My approved discount is missing from the tuition assessment shown in the portal.',
    ]);
    $request = StudentTuitionUpdateRequest::query()->sole();
    $batch = TuitionAdjustmentBatch::query()->create([
        'public_id' => (string) Str::uuid(), 'actor_user_id' => $finance->id, 'source' => 'student', 'status' => 'completed', 'recorded_count' => 1,
    ]);
    $adjustment = TuitionAdjustment::query()->create([
        'batch_id' => $batch->id, 'actor_user_id' => $finance->id, 'student_enrollment_id' => $enrollment->id, 'student_tuition_id' => $tuition->id,
        'client_row_id' => 'request-discount', 'idempotency_key' => hash('sha256', 'request-discount'), 'source' => 'student',
        'reason' => 'Applied the approved student discount.', 'before_snapshot' => [], 'after_snapshot' => [], 'configuration_snapshot' => [],
    ]);

    $this->actingAs($finance)->post(route('administrators.finance.tuition-update-requests.claim', $request));
    $this->actingAs($finance)->post(route('administrators.finance.tuition-update-requests.resolve-adjustment', $request), [
        'tuition_adjustment_id' => $adjustment->id, 'resolution_note' => 'Your approved discount was applied and the assessment was refreshed.',
    ])->assertRedirect();

    expect($request->refresh()->status)->toBe(StudentTuitionUpdateRequest::StatusResolved)
        ->and($request->tuition_adjustment_id)->toBe($adjustment->id)
        ->and($request->events()->count())->toBe(3);
    Notification::assertSentTo($studentUser, StudentTuitionUpdateRequestReviewedNotification::class);
});

it('lets Finance record a new verified payment transaction and resolve a missing payment request', function (): void {
    Notification::fake();
    [$studentUser, $student, $enrollment, $tuition] = tuitionRequestStudent();
    $finance = tuitionRequestFinanceUser();

    $this->actingAs($studentUser)->post(route('student.tuition.update-requests.store'), [
        'school_year' => '2026 - 2027', 'semester' => 1, 'concern_type' => 'missing_payment',
        'receipt_number' => 'OR-MANUAL-789', 'details' => 'I paid ₱3,500 at the cashier counter but it does not appear on my tuition page.',
    ]);
    $request = StudentTuitionUpdateRequest::query()->sole();

    $this->actingAs($finance)->post(route('administrators.finance.tuition-update-requests.claim', $request))->assertRedirect();

    $this->actingAs($finance)->post(route('administrators.finance.tuition-update-requests.record-and-resolve-payment', $request), [
        'amount' => 3500.00,
        'payment_method' => 'Cash',
        'reference_number' => 'OR-MANUAL-789',
        'resolution_note' => 'We verified your counter receipt and recorded the ₱3,500.00 payment into your tuition account.',
    ])->assertRedirect();

    $request->refresh();
    expect($request->status)->toBe(StudentTuitionUpdateRequest::StatusResolved)
        ->and($request->resolved_transaction_id)->not->toBeNull()
        ->and($request->resolvedTransaction)->not->toBeNull()
        ->and((float) $request->resolvedTransaction->raw_total_amount)->toBe(3500.00)
        ->and($request->events()->where('event', 'resolved_with_recorded_payment')->count())->toBe(1);

    expect((float) $tuition->refresh()->total_balance)->toBe(6500.00);
    Notification::assertSentTo($studentUser, StudentTuitionUpdateRequestReviewedNotification::class);
});

it('lets Finance resolve a request with a verified student payment when receipt number differs if allowed', function (): void {
    Notification::fake();
    [$studentUser, $student, $enrollment] = tuitionRequestStudent();
    $finance = tuitionRequestFinanceUser();

    $this->actingAs($studentUser)->post(route('student.tuition.update-requests.store'), [
        'school_year' => '2026 - 2027', 'semester' => 1, 'concern_type' => 'missing_payment',
        'receipt_number' => 'OR-TYPO-999', 'details' => 'Payment made via bank transfer.',
    ]);
    $request = StudentTuitionUpdateRequest::query()->sole();

    $transaction = Transaction::query()->create([
        'description' => 'Tuition payment', 'status' => 'paid', 'transaction_date' => now(),
        'settlements' => ['tuition_fee' => 2000], 'invoicenumber' => 'OR-ACTUAL-1000',
    ]);
    StudentTransaction::query()->create([
        'student_id' => $student->id, 'student_enrollment_id' => $enrollment->id, 'transaction_id' => $transaction->id, 'amount' => 2000, 'status' => 'paid',
    ]);

    $this->actingAs($finance)->post(route('administrators.finance.tuition-update-requests.claim', $request));
    $this->actingAs($finance)->post(route('administrators.finance.tuition-update-requests.resolve-payment', $request), [
        'transaction_id' => $transaction->id,
        'allow_mismatch' => true,
        'resolution_note' => 'Linked to transaction with corrected OR number.',
    ])->assertRedirect();

    $request->refresh();
    expect($request->status)->toBe(StudentTuitionUpdateRequest::StatusResolved)
        ->and($request->resolved_transaction_id)->toBe($transaction->id)
        ->and($request->receipt_number)->toBe('OR-ACTUAL-1000');
});

it('sends email and database notification and builds expected mail message', function (): void {
    [$studentUser] = tuitionRequestStudent();
    $finance = tuitionRequestFinanceUser();

    $this->actingAs($studentUser)->post(route('student.tuition.update-requests.store'), [
        'school_year' => '2026 - 2027', 'semester' => 1, 'concern_type' => 'other',
        'details' => 'Reviewing my assessment breakdown.',
    ]);
    $request = StudentTuitionUpdateRequest::query()->sole();

    $this->actingAs($finance)->post(route('administrators.finance.tuition-update-requests.claim', $request));
    $this->actingAs($finance)->post(route('administrators.finance.tuition-update-requests.reject', $request), [
        'resolution_note' => 'Please visit the cashier counter to complete the assessment correction.',
    ]);

    $request->refresh();
    $notification = new StudentTuitionUpdateRequestReviewedNotification($request);

    expect($notification->via($studentUser))->toBe(['database', 'mail']);

    $mail = $notification->toMail($studentUser);
    expect($mail->subject)->toContain('Tuition Update Request #'.$request->id)
        ->and($mail->introLines)->toContain('Finance note: Please visit the cashier counter to complete the assessment correction.');
});

it('lets Finance resend email notifications on resolved requests', function (): void {
    Notification::fake();
    [$studentUser, $student, $enrollment] = tuitionRequestStudent();
    $finance = tuitionRequestFinanceUser();

    $this->actingAs($studentUser)->post(route('student.tuition.update-requests.store'), [
        'school_year' => '2026 - 2027', 'semester' => 1, 'concern_type' => 'missing_payment',
        'receipt_number' => 'OR-2026-777', 'details' => 'Cashier payment not reflected.',
    ]);
    $request = StudentTuitionUpdateRequest::query()->sole();

    $transaction = Transaction::query()->create([
        'description' => 'Tuition payment', 'status' => 'paid', 'transaction_date' => now(),
        'settlements' => ['tuition_fee' => 1000], 'invoicenumber' => 'OR-2026-777',
    ]);
    StudentTransaction::query()->create([
        'student_id' => $student->id, 'student_enrollment_id' => $enrollment->id, 'transaction_id' => $transaction->id, 'amount' => 1000, 'status' => 'paid',
    ]);

    $this->actingAs($finance)->post(route('administrators.finance.tuition-update-requests.claim', $request));
    $this->actingAs($finance)->post(route('administrators.finance.tuition-update-requests.resolve-payment', $request), [
        'transaction_id' => $transaction->id, 'resolution_note' => 'Payment verified.',
    ]);

    // Resend email
    $this->actingAs($finance)
        ->post(route('administrators.finance.tuition-update-requests.resend-email', $request))
        ->assertRedirect()
        ->assertSessionHas('flash.success');

    Notification::assertSentTo($studentUser, StudentTuitionUpdateRequestReviewedNotification::class);

    expect($request->refresh()->events()->where('event', 'notification_email_resent')->count())->toBe(1);
});

it('prevents non-finance users or pending requests from resending notifications', function (): void {
    [$studentUser] = tuitionRequestStudent();
    $finance = tuitionRequestFinanceUser();

    $this->actingAs($studentUser)->post(route('student.tuition.update-requests.store'), [
        'school_year' => '2026 - 2027', 'semester' => 1, 'concern_type' => 'other',
        'details' => 'Reviewing my assessment breakdown.',
    ]);
    $request = StudentTuitionUpdateRequest::query()->sole();

    // Student cannot resend
    $this->actingAs($studentUser)
        ->post(route('administrators.finance.tuition-update-requests.resend-email', $request))
        ->assertForbidden();

    // Pending request cannot resend
    $this->actingAs($finance)
        ->post(route('administrators.finance.tuition-update-requests.resend-email', $request))
        ->assertStatus(422);
});

it('lets a student specify an amount and upload a receipt image', function (): void {
    \Illuminate\Support\Facades\Storage::fake('public');
    [$user, $student] = tuitionRequestStudent();

    $file = \Illuminate\Http\UploadedFile::fake()->image('my_receipt.jpg', 600, 800);

    $this->actingAs($user)
        ->post(route('student.tuition.update-requests.store'), [
            'school_year' => '2026 - 2027',
            'semester' => 1,
            'concern_type' => 'missing_payment',
            'amount' => 5000.00,
            'receipt_number' => 'OR-UPLOAD-100',
            'receipt_image' => $file,
            'details' => 'I paid 5,000 pesos at the counter, here is my receipt photo.',
        ])->assertRedirect();

    $request = StudentTuitionUpdateRequest::query()->with('receipts')->sole();
    expect((float) $request->amount)->toBe(5000.00)
        ->and($request->receipt_number)->toBe('OR-UPLOAD-100')
        ->and($request->receipts)->toHaveCount(1);

    $receipt = $request->receipts->first();
    expect($receipt->receipt_number)->toBe('OR-UPLOAD-100')
        ->and((float) $receipt->amount)->toBe(5000.00)
        ->and($receipt->image_path)->not->toBeNull();

    \Illuminate\Support\Facades\Storage::disk('public')->assertExists($receipt->image_path);
});

it('lets a student submit two receipts with photos and amounts', function (): void {
    \Illuminate\Support\Facades\Storage::fake('public');
    [$user, $student] = tuitionRequestStudent();

    $file1 = \Illuminate\Http\UploadedFile::fake()->image('receipt1.png');
    $file2 = \Illuminate\Http\UploadedFile::fake()->image('receipt2.png');

    $this->actingAs($user)
        ->post(route('student.tuition.update-requests.store'), [
            'school_year' => '2026 - 2027',
            'semester' => 1,
            'concern_type' => 'missing_payment',
            'details' => 'I made two separate payments of 5,000 each for my downpayment.',
            'receipts' => [
                ['receipt_number' => 'OR-224103', 'amount' => 5000.00, 'image' => $file1],
                ['receipt_number' => 'OR-226432', 'amount' => 5000.00, 'image' => $file2],
            ],
        ])->assertRedirect();

    $request = StudentTuitionUpdateRequest::query()->with('receipts')->sole();
    expect((float) $request->amount)->toBe(10000.00)
        ->and($request->receipts)->toHaveCount(2)
        ->and($request->receipt_number)->toContain('OR-224103')
        ->and($request->receipt_number)->toContain('OR-226432');

    foreach ($request->receipts as $receipt) {
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($receipt->image_path);
    }
});

it('lets Finance adjust an existing transaction amount to correct downpayment error and reconciles balance', function (): void {
    Notification::fake();
    [$studentUser, $student, $enrollment, $tuition] = tuitionRequestStudent();
    $finance = tuitionRequestFinanceUser();

    // Student tuition overall is 15,970
    $tuition->overall_tuition = 15970.00;
    $tuition->total_tuition = 11750.00;
    $tuition->total_lectures = 9750.00;
    $tuition->total_laboratory = 2000.00;
    $tuition->total_miscelaneous_fees = 3700.00;
    $tuition->downpayment = 10000.00;
    $tuition->paid = 10450.00;
    $tuition->total_balance = 5520.00;
    $tuition->status = 'Downpayment';
    $tuition->save();

    // Transaction was recorded as 10,450.00 instead of 10,000.00
    $transaction = Transaction::query()->create([
        'description' => 'Downpayment for student Tuition',
        'status' => 'paid',
        'transaction_date' => now(),
        'settlements' => ['tuition_fee' => 10450.00],
        'invoicenumber' => '226432',
    ]);
    $stTx = StudentTransaction::query()->create([
        'student_id' => $student->id,
        'student_enrollment_id' => $enrollment->id,
        'transaction_id' => $transaction->id,
        'amount' => 10450.00,
        'status' => 'paid',
    ]);
    \App\Models\PaymentAllocation::query()->create([
        'transaction_id' => $transaction->id,
        'student_transaction_id' => $stTx->id,
        'student_id' => $student->id,
        'student_enrollment_id' => $enrollment->id,
        'student_tuition_id' => $tuition->id,
        'charge_category' => 'tuition',
        'target_type' => 'assessment',
        'amount' => 10450.00,
        'notes' => 'Tuition downpayment',
    ]);

    $this->actingAs($studentUser)->post(route('student.tuition.update-requests.store'), [
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'concern_type' => 'missing_payment',
        'amount' => 10000.00,
        'receipt_number' => '226432',
        'details' => 'My downpayment was recorded as 10,450 instead of 10,000.',
    ]);
    $request = StudentTuitionUpdateRequest::query()->sole();

    $this->actingAs($finance)->post(route('administrators.finance.tuition-update-requests.claim', $request))->assertRedirect();

    // Finance adjusts the transaction from 10450 to 10000
    $this->actingAs($finance)->post(route('administrators.finance.tuition-update-requests.adjust-and-resolve-payment', $request), [
        'transaction_id' => $transaction->id,
        'amount' => 10000.00,
        'reference_number' => '226432',
        'resolution_note' => 'Adjusted cashier transaction #226432 from ₱10,450.00 to ₱10,000.00 to reflect exact downpayment.',
    ])->assertRedirect();

    $request->refresh();
    expect($request->status)->toBe(StudentTuitionUpdateRequest::StatusResolved)
        ->and($request->resolved_transaction_id)->toBe($transaction->id)
        ->and($request->events()->where('event', 'resolved_with_adjusted_transaction')->count())->toBe(1);

    // Verify transaction amount is 10,000
    $transaction->refresh();
    expect((float) $transaction->raw_total_amount)->toBe(10000.00);

    // Verify student transaction amount is 10,000
    $stTx->refresh();
    expect((float) $stTx->amount)->toBe(10000.00);

    // Verify ledger balance is 15,970 - 10,000 = 5,970
    $tuition->refresh();
    expect((float) $tuition->paid)->toBe(10000.00)
        ->and((float) $tuition->total_balance)->toBe(5970.00);

    Notification::assertSentTo($studentUser, StudentTuitionUpdateRequestReviewedNotification::class);
});
