<?php

declare(strict_types=1);

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
