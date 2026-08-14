<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Models\TuitionAdjustment;
use App\Models\User;
use App\Notifications\StudentTuitionAdjustedNotification;
use App\Services\TuitionAdjustmentService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

function tuitionAdjustmentUser(): User
{
    $user = User::factory()->create(['role' => UserRole::AccountingOfficer]);
    foreach (['view_tuition_fees', 'manage_tuition_fees'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $user->givePermissionTo(['view_tuition_fees', 'manage_tuition_fees']);

    return $user;
}

/** @return array{student: Student, enrollment: StudentEnrollment, tuition: StudentTuition} */
function tuitionAdjustmentEnrollment(float $total = 14950, float $paid = 0): array
{
    $course = Course::factory()->create(['code' => 'BSIT-'.Str::upper(Str::random(4))]);
    $student = Student::factory()->create(['course_id' => $course->id, 'student_type' => 'college']);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'academic_year' => 1,
    ]);
    $tuition = StudentTuition::query()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'school_year' => $enrollment->school_year,
        'semester' => 1,
        'academic_year' => 1,
        'total_tuition' => $total,
        'total_lectures' => $total,
        'total_laboratory' => 0,
        'total_miscelaneous_fees' => 0,
        'overall_tuition' => $total,
        'total_balance' => max(0, $total - $paid),
        'paid' => $paid,
        'discount' => 0,
        'downpayment' => 0,
        'status' => 'pending',
    ]);

    return compact('student', 'enrollment', 'tuition');
}

it('shows the tuition adjustment workspace only with tuition permission', function (): void {
    $authorized = tuitionAdjustmentUser();
    tuitionAdjustmentEnrollment();

    $this->actingAs($authorized)
        ->get(portalUrlForAdministrators('/administrators/finance/tuition-adjustments?school_year=2026%20-%202027&semester=1'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/finance/tuition-adjustments')
            ->has('rows', 1)
            ->where('rows.0.total_fees', 14950)
            ->where('rows.0.installments.0.amount', 4500)
            ->where('workspace_layout', 'inspector')
        );

    $unauthorized = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($unauthorized)
        ->get(portalUrlForAdministrators('/administrators/finance/tuition-adjustments'))
        ->assertForbidden();
});

it('allows an enum-only super admin to use the tuition adjustment workspace', function (): void {
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
    tuitionAdjustmentEnrollment();

    $this->actingAs($superAdmin)
        ->get(portalUrlForAdministrators('/administrators/finance/tuition-adjustments?school_year=2026%20-%202027&semester=1'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/finance/tuition-adjustments')
            ->has('rows', 1)
        );
});

it('records a reconciled adjustment, installments, audit, and student notifications', function (): void {
    Notification::fake();
    $actor = tuitionAdjustmentUser();
    ['student' => $student, 'enrollment' => $enrollment] = tuitionAdjustmentEnrollment(18000, 3500);
    $studentUser = User::factory()->create(['role' => UserRole::Student, 'email' => $student->email]);
    $student->forceFill(['user_id' => $studentUser->id])->save();
    $canonical = app(TuitionAdjustmentService::class)->serialize($enrollment);
    $batchKey = (string) Str::uuid();

    $payload = [
        'batch_key' => $batchKey,
        'source' => 'workspace',
        'reason' => 'Reconciled against the signed tuition ledger.',
        'rows' => [[
            'client_row_id' => 'row-1',
            'enrollment_id' => $enrollment->id,
            'tuition_id' => $canonical['tuition_id'],
            'state_hash' => $canonical['state_hash'],
            'total_fees' => 18450,
            'opening_paid' => 3500,
            'balance' => 14950,
            'lecture' => 15950,
            'laboratory' => 2000,
            'miscellaneous' => 500,
            'discount' => 0,
            'required_downpayment' => 3000,
            'installments' => ['prelim' => 4500, 'midterm' => 4500, 'finals' => 5950],
        ]],
    ];

    $this->actingAs($actor)
        ->postJson(portalUrlForAdministrators('/administrators/finance/tuition-adjustments/batch'), $payload)
        ->assertSuccessful()
        ->assertJsonPath('rows.0.status', 'recorded')
        ->assertJsonPath('rows.0.canonical.balance_due', 14950);

    $tuition = $enrollment->studentTuition()->firstOrFail();
    expect((float) $tuition->overall_tuition)->toBe(18450.0)
        ->and((float) $tuition->paid)->toBe(3500.0)
        ->and((float) $tuition->total_balance)->toBe(14950.0)
        ->and($tuition->installments()->orderBy('sequence')->pluck('amount')->map(fn ($amount): float => (float) $amount)->all())
        ->toBe([4500.0, 4500.0, 5950.0])
        ->and(TuitionAdjustment::query()->sole()->reason)->toBe('Reconciled against the signed tuition ledger.');

    Notification::assertSentTo($studentUser, StudentTuitionAdjustedNotification::class);
    Notification::assertSentOnDemand(StudentTuitionAdjustedNotification::class);

    $freshCanonical = app(TuitionAdjustmentService::class)->serialize($enrollment->refresh());
    $payload['rows'][0]['state_hash'] = $freshCanonical['state_hash'];
    $this->actingAs($actor)
        ->postJson(portalUrlForAdministrators('/administrators/finance/tuition-adjustments/batch'), $payload)
        ->assertJsonPath('rows.0.status', 'duplicate');
});

it('keeps credits signed and rejects contradictory paper columns', function (): void {
    $actor = tuitionAdjustmentUser();
    ['enrollment' => $enrollment] = tuitionAdjustmentEnrollment(100, 0);
    $canonical = app(TuitionAdjustmentService::class)->serialize($enrollment);

    $baseRow = [
        'client_row_id' => 'credit-row',
        'enrollment_id' => $enrollment->id,
        'tuition_id' => $canonical['tuition_id'],
        'state_hash' => $canonical['state_hash'],
        'total_fees' => 100,
        'opening_paid' => 150,
        'balance' => -50,
        'installments' => ['prelim' => 0, 'midterm' => 0, 'finals' => 0],
    ];

    $this->actingAs($actor)
        ->postJson(portalUrlForAdministrators('/administrators/finance/tuition-adjustments/batch'), [
            'batch_key' => (string) Str::uuid(),
            'reason' => 'Opening credit reconciliation.',
            'rows' => [$baseRow],
        ])
        ->assertJsonPath('rows.0.status', 'recorded')
        ->assertJsonPath('rows.0.canonical.credit', 50)
        ->assertJsonPath('rows.0.canonical.balance_due', 0);

    ['enrollment' => $otherEnrollment] = tuitionAdjustmentEnrollment(100, 0);
    $other = app(TuitionAdjustmentService::class)->serialize($otherEnrollment);
    $baseRow['client_row_id'] = 'bad-row';
    $baseRow['enrollment_id'] = $otherEnrollment->id;
    $baseRow['tuition_id'] = $other['tuition_id'];
    $baseRow['state_hash'] = $other['state_hash'];
    $baseRow['balance'] = 10;

    $this->actingAs($actor)
        ->postJson(portalUrlForAdministrators('/administrators/finance/tuition-adjustments/batch'), [
            'batch_key' => (string) Str::uuid(),
            'reason' => 'Contradictory ledger row.',
            'rows' => [$baseRow],
        ])
        ->assertJsonPath('rows.0.status', 'rejected');
});

it('commits valid rows independently while rejecting stale rows in the same batch', function (): void {
    Notification::fake();
    $actor = tuitionAdjustmentUser();
    ['enrollment' => $validEnrollment] = tuitionAdjustmentEnrollment(10000, 1000);
    ['enrollment' => $staleEnrollment] = tuitionAdjustmentEnrollment(12000, 2000);
    $valid = app(TuitionAdjustmentService::class)->serialize($validEnrollment);
    $stale = app(TuitionAdjustmentService::class)->serialize($staleEnrollment);

    $response = $this->actingAs($actor)
        ->postJson(portalUrlForAdministrators('/administrators/finance/tuition-adjustments/batch'), [
            'batch_key' => (string) Str::uuid(),
            'reason' => 'Batch reconciliation with one stale row.',
            'rows' => [
                [
                    'client_row_id' => 'valid-row',
                    'enrollment_id' => $validEnrollment->id,
                    'tuition_id' => $valid['tuition_id'],
                    'state_hash' => $valid['state_hash'],
                    'total_fees' => 10500,
                    'opening_paid' => 1000,
                    'balance' => 9500,
                    'installments' => ['prelim' => 2900, 'midterm' => 2900, 'finals' => 3700],
                ],
                [
                    'client_row_id' => 'stale-row',
                    'enrollment_id' => $staleEnrollment->id,
                    'tuition_id' => $stale['tuition_id'],
                    'state_hash' => str_repeat('0', 64),
                    'total_fees' => 12000,
                    'opening_paid' => 2000,
                    'balance' => 10000,
                    'installments' => ['prelim' => 3000, 'midterm' => 3000, 'finals' => 4000],
                ],
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('rows.0.status', 'recorded')
        ->assertJsonPath('rows.1.status', 'rejected');

    expect((float) $validEnrollment->studentTuition()->firstOrFail()->overall_tuition)->toBe(10500.0)
        ->and((float) $staleEnrollment->studentTuition()->firstOrFail()->overall_tuition)->toBe(12000.0)
        ->and(TuitionAdjustment::query()->count())->toBe(1);

    $response->assertJsonPath('rows.1.message', 'This tuition changed after it was loaded. Refresh the row and try again.');
});

it('records delivery warnings without rolling back an adjustment', function (): void {
    Notification::fake();
    $actor = tuitionAdjustmentUser();
    ['student' => $student, 'enrollment' => $enrollment] = tuitionAdjustmentEnrollment(1000, 0);
    $student->forceFill(['email' => null, 'user_id' => null])->save();
    $canonical = app(TuitionAdjustmentService::class)->serialize($enrollment);

    $this->actingAs($actor)
        ->postJson(portalUrlForAdministrators('/administrators/finance/tuition-adjustments/batch'), [
            'batch_key' => (string) Str::uuid(),
            'reason' => 'Corrected assessment without notification recipients.',
            'rows' => [[
                'client_row_id' => 'warning-row',
                'enrollment_id' => $enrollment->id,
                'tuition_id' => $canonical['tuition_id'],
                'state_hash' => $canonical['state_hash'],
                'total_fees' => 1100,
                'opening_paid' => 0,
                'balance' => 1100,
                'installments' => ['prelim' => 300, 'midterm' => 300, 'finals' => 500],
            ]],
        ])
        ->assertJsonPath('rows.0.status', 'recorded')
        ->assertJsonPath('rows.0.delivery_status.database', 'unavailable')
        ->assertJsonPath('rows.0.delivery_status.mail', 'unavailable')
        ->assertJsonCount(2, 'rows.0.warnings');

    expect((float) $enrollment->studentTuition()->firstOrFail()->overall_tuition)->toBe(1100.0);
});
