<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Exceptions\FinancialAuditLockException;
use App\Models\AdditionalFee;
use App\Models\AdminTransaction;
use App\Models\AssessmentRevision;
use App\Models\Course;
use App\Models\GeneralSetting;
use App\Models\PaymentAllocation;
use App\Models\School;
use App\Models\StatementOfAccountIssuance;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTransaction;
use App\Models\StudentTuition;
use App\Models\StudentTuitionUpdateRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Policies\StudentEnrollmentPolicy;
use App\Policies\StudentPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

it('restricts database deletion of student_enrollment when student_tuition exists', function (): void {
    $course = Course::factory()->create();
    $student = Student::factory()->create();
    $enrollment = StudentEnrollment::create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'semester' => 1,
        'academic_year' => 1,
        'school_year' => '2026-2027',
        'status' => 'enrolled',
    ]);

    StudentTuition::create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'semester' => 1,
        'school_year' => '2026-2027',
        'academic_year' => 1,
        'overall_tuition' => 12000,
        'total_balance' => 12000,
    ]);

    expect(function () use ($enrollment): void {
        DB::table('student_enrollment')->where('id', $enrollment->id)->delete();
    })->toThrow(QueryException::class);

    expect(DB::table('student_enrollment')->where('id', $enrollment->id)->exists())->toBeTrue();
    expect(DB::table('student_tuition')->where('enrollment_id', $enrollment->id)->exists())->toBeTrue();
});

it('restricts database deletion of student_enrollment when additional_fees exist', function (): void {
    $course = Course::factory()->create();
    $student = Student::factory()->create();
    $enrollment = StudentEnrollment::create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'semester' => 1,
        'academic_year' => 1,
        'school_year' => '2026-2027',
        'status' => 'enrolled',
    ]);

    AdditionalFee::create([
        'enrollment_id' => $enrollment->id,
        'fee_name' => 'Laboratory Fee',
        'amount' => 1500.00,
    ]);

    expect(function () use ($enrollment): void {
        DB::table('student_enrollment')->where('id', $enrollment->id)->delete();
    })->toThrow(QueryException::class);

    expect(DB::table('additional_fees')->where('enrollment_id', $enrollment->id)->exists())->toBeTrue();
});

it('restricts database deletion of student_enrollment when assessment_revisions exist', function (): void {
    $course = Course::factory()->create();
    $student = Student::factory()->create();
    $enrollment = StudentEnrollment::create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'semester' => 1,
        'academic_year' => 1,
        'school_year' => '2026-2027',
        'status' => 'enrolled',
    ]);

    $tuition = StudentTuition::create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'semester' => 1,
        'school_year' => '2026-2027',
        'academic_year' => 1,
        'overall_tuition' => 12000,
        'total_balance' => 12000,
    ]);

    AssessmentRevision::create([
        'public_id' => (string) Str::uuid(),
        'student_tuition_id' => $tuition->id,
        'student_enrollment_id' => $enrollment->id,
        'revision_number' => 1,
        'fingerprint' => hash('sha256', 'test-revision'),
    ]);

    expect(function () use ($enrollment): void {
        DB::table('student_enrollment')->where('id', $enrollment->id)->delete();
    })->toThrow(QueryException::class);

    expect(function () use ($tuition): void {
        DB::table('student_tuition')->where('id', $tuition->id)->delete();
    })->toThrow(QueryException::class);
});

it('restricts database deletion of students when statement_of_account_issuances exist', function (): void {
    $student = Student::factory()->create();

    StatementOfAccountIssuance::create([
        'uuid' => (string) Str::uuid(),
        'student_id' => $student->id,
        'document_number' => 'SOA-2026-0001',
        'verification_token_hash' => hash('sha256', 'token-1'),
        'integrity_signature' => hash('sha256', 'sig-1'),
        'snapshot' => ['tuition' => 10000],
        'issued_at' => now(),
    ]);

    expect(function () use ($student): void {
        DB::table('students')->where('id', $student->id)->delete();
    })->toThrow(QueryException::class);

    expect(DB::table('statement_of_account_issuances')->where('student_id', $student->id)->exists())->toBeTrue();
});

it('restricts database deletion of students and transactions when payment_allocations exist', function (): void {
    $student = Student::factory()->create();
    $transaction = Transaction::create([
        'description' => 'Tuition payment',
        'status' => 'completed',
        'transaction_date' => now(),
        'transaction_type' => 'tuition',
    ]);

    PaymentAllocation::create([
        'student_id' => $student->id,
        'transaction_id' => $transaction->id,
        'amount' => 5000.00,
    ]);

    expect(function () use ($student): void {
        DB::table('students')->where('id', $student->id)->delete();
    })->toThrow(QueryException::class);

    expect(function () use ($transaction): void {
        DB::table('transactions')->where('id', $transaction->id)->delete();
    })->toThrow(QueryException::class);
});

it('restricts database deletion of transactions and users when admin_transactions exist', function (): void {
    $user = User::factory()->create();
    $transaction = Transaction::create([
        'description' => 'Tuition payment',
        'status' => 'completed',
        'transaction_date' => now(),
        'transaction_type' => 'tuition',
    ]);

    AdminTransaction::create([
        'admin_id' => $user->id,
        'transaction_id' => $transaction->id,
        'amount' => 2500.00,
        'type' => 'credit',
        'status' => 'completed',
    ]);

    expect(function () use ($user): void {
        DB::table('users')->where('id', $user->id)->delete();
    })->toThrow(QueryException::class);

    expect(function () use ($transaction): void {
        DB::table('transactions')->where('id', $transaction->id)->delete();
    })->toThrow(QueryException::class);
});

it('restricts database deletion of transactions when student_transactions exist', function (): void {
    $student = Student::factory()->create();
    $transaction = Transaction::create([
        'description' => 'Tuition payment',
        'status' => 'completed',
        'transaction_date' => now(),
        'transaction_type' => 'tuition',
    ]);

    StudentTransaction::create([
        'student_id' => $student->id,
        'transaction_id' => $transaction->id,
        'amount' => 3000,
        'status' => 'completed',
    ]);

    expect(function () use ($transaction): void {
        DB::table('transactions')->where('id', $transaction->id)->delete();
    })->toThrow(QueryException::class);
});

it('restricts database deletion of users and students when tuition_update_requests exist', function (): void {
    $user = User::factory()->create();
    $student = Student::factory()->create();

    StudentTuitionUpdateRequest::create([
        'submitted_by_user_id' => $user->id,
        'student_id' => $student->id,
        'school_year' => '2026-2027',
        'semester' => 1,
        'concern_type' => StudentTuitionUpdateRequest::ConcernMissingPayment,
        'details' => 'Payment receipt verification request',
    ]);

    expect(function () use ($user): void {
        DB::table('users')->where('id', $user->id)->delete();
    })->toThrow(QueryException::class);

    expect(function () use ($student): void {
        DB::table('students')->where('id', $student->id)->delete();
    })->toThrow(QueryException::class);
});

it('sets general_settings.school_id to null when school is deleted instead of cascading', function (): void {
    $school = School::create([
        'name' => 'Test Academy',
        'code' => 'TA-01',
    ]);

    $setting = GeneralSetting::create([
        'school_id' => $school->id,
        'site_name' => 'Test Academy',
    ]);

    DB::table('schools')->where('id', $school->id)->delete();

    $setting->refresh();
    expect($setting->school_id)->toBeNull();
    expect(GeneralSetting::whereKey($setting->id)->exists())->toBeTrue();
});

it('throws FinancialAuditLockException on Student::forceDelete when financial records exist', function (): void {
    $student = Student::factory()->create();

    StatementOfAccountIssuance::create([
        'uuid' => (string) Str::uuid(),
        'student_id' => $student->id,
        'document_number' => 'SOA-2026-0002',
        'verification_token_hash' => hash('sha256', 'token-2'),
        'integrity_signature' => hash('sha256', 'sig-2'),
        'snapshot' => ['tuition' => 15000],
        'issued_at' => now(),
    ]);

    expect(fn () => $student->forceDelete())->toThrow(FinancialAuditLockException::class);
    expect(Student::whereKey($student->id)->exists())->toBeTrue();
});

it('allows Student::forceDelete when student has zero financial records', function (): void {
    $student = Student::factory()->create();

    $student->forceDelete();

    expect(Student::withTrashed()->whereKey($student->id)->exists())->toBeFalse();
});

it('allows Student::delete soft delete even with financial records', function (): void {
    $student = Student::factory()->create();

    StatementOfAccountIssuance::create([
        'uuid' => (string) Str::uuid(),
        'student_id' => $student->id,
        'document_number' => 'SOA-2026-0003',
        'verification_token_hash' => hash('sha256', 'token-3'),
        'integrity_signature' => hash('sha256', 'sig-3'),
        'snapshot' => ['tuition' => 15000],
        'issued_at' => now(),
    ]);

    $student->delete();

    expect($student->trashed())->toBeTrue();
    expect(StatementOfAccountIssuance::where('student_id', $student->id)->exists())->toBeTrue();
});

it('throws FinancialAuditLockException on StudentEnrollment::forceDelete when financial records exist', function (): void {
    $course = Course::factory()->create();
    $student = Student::factory()->create();
    $enrollment = StudentEnrollment::create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'semester' => 1,
        'academic_year' => 1,
        'school_year' => '2026-2027',
        'status' => 'enrolled',
    ]);

    AdditionalFee::create([
        'enrollment_id' => $enrollment->id,
        'fee_name' => 'Graduation Fee',
        'amount' => 2000.00,
    ]);

    expect(fn () => $enrollment->forceDelete())->toThrow(FinancialAuditLockException::class);
    expect(StudentEnrollment::whereKey($enrollment->id)->exists())->toBeTrue();
});

it('prevents forceDelete authorization in policies when financial records exist', function (): void {
    Permission::firstOrCreate(['name' => 'ForceDelete:Student', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'ForceDelete:StudentEnrollment', 'guard_name' => 'web']);

    $admin = User::factory()->create(['role' => UserRole::Admin->value]);
    $admin->givePermissionTo('ForceDelete:Student');
    $admin->givePermissionTo('ForceDelete:StudentEnrollment');

    $student = Student::factory()->create();
    StatementOfAccountIssuance::create([
        'uuid' => (string) Str::uuid(),
        'student_id' => $student->id,
        'document_number' => 'SOA-2026-0004',
        'verification_token_hash' => hash('sha256', 'token-4'),
        'integrity_signature' => hash('sha256', 'sig-4'),
        'snapshot' => ['tuition' => 15000],
        'issued_at' => now(),
    ]);

    $studentPolicy = new StudentPolicy();
    expect($studentPolicy->forceDelete($admin, $student))->toBeFalse();

    $cleanStudent = Student::factory()->create();
    expect($studentPolicy->forceDelete($admin, $cleanStudent))->toBeTrue();

    $course = Course::factory()->create();
    $enrollment = StudentEnrollment::create([
        'student_id' => $cleanStudent->id,
        'course_id' => $course->id,
        'semester' => 1,
        'academic_year' => 1,
        'school_year' => '2026-2027',
        'status' => 'enrolled',
    ]);
    AdditionalFee::create([
        'enrollment_id' => $enrollment->id,
        'fee_name' => 'Lab Fee',
        'amount' => 500.00,
    ]);

    $enrollmentPolicy = new StudentEnrollmentPolicy();
    expect($enrollmentPolicy->forceDelete($admin, $enrollment))->toBeFalse();
});

it('rejects controller forceDestroy when student has financial records', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin->value]);
    $student = Student::factory()->create();

    StatementOfAccountIssuance::create([
        'uuid' => (string) Str::uuid(),
        'student_id' => $student->id,
        'document_number' => 'SOA-2026-0005',
        'verification_token_hash' => hash('sha256', 'token-5'),
        'integrity_signature' => hash('sha256', 'sig-5'),
        'snapshot' => ['tuition' => 15000],
        'issued_at' => now(),
    ]);

    $response = $this->actingAs($admin)
        ->from(route('administrators.students.index'))
        ->delete(route('administrators.students.force-destroy', ['student' => $student->id]));

    $response->assertRedirect(route('administrators.students.index'));
    $response->assertSessionHas('error');

    expect(Student::whereKey($student->id)->exists())->toBeTrue();
    expect(StatementOfAccountIssuance::where('student_id', $student->id)->exists())->toBeTrue();
});

it('rejects controller forceDestroy when enrollment has financial records', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin->value]);
    $course = Course::factory()->create();
    $student = Student::factory()->create();
    $enrollment = StudentEnrollment::create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'semester' => 1,
        'academic_year' => 1,
        'school_year' => '2026-2027',
        'status' => 'enrolled',
    ]);

    AdditionalFee::create([
        'enrollment_id' => $enrollment->id,
        'fee_name' => 'Athletics Fee',
        'amount' => 800.00,
    ]);

    $response = $this->actingAs($admin)
        ->from(route('administrators.enrollments.index'))
        ->delete(route('administrators.enrollments.force-destroy', ['enrollment' => $enrollment->id]));

    $response->assertRedirect(route('administrators.enrollments.index'));
    $response->assertSessionHas('flash.error');

    expect(StudentEnrollment::whereKey($enrollment->id)->exists())->toBeTrue();
    expect(AdditionalFee::where('enrollment_id', $enrollment->id)->exists())->toBeTrue();
});
