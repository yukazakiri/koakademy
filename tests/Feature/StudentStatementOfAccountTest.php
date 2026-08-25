<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Features\Toggles\StudentTuition as StudentTuitionFeature;
use App\Jobs\GenerateStudentSoaPdfJob;
use App\Models\Course;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\StatementOfAccountIssuance;
use App\Models\Student;
use App\Models\StudentTuition;
use App\Models\User;
use App\Services\SchoolBrandingService;
use App\Services\StatementOfAccountService;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia;
use Laravel\Pennant\Feature;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    School::factory()->create();
    Feature::activateForEveryone(StudentTuitionFeature::class);
    config(['inertia.testing.ensure_pages_exist' => false]);
});

it('resolves the configured school portal logo through its public disk URL', function (): void {
    GeneralSetting::factory()->create(['school_portal_logo' => 'branding/official-seal.png']);

    $logo = app(SchoolBrandingService::class)->resolve()['logo'];

    expect($logo)->toContain('branding/official-seal.png');
});

it('generates an immutable official statement for the authenticated student', function (): void {
    Bus::fake();
    GeneralSetting::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Student->value]);
    $course = Course::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id, 'email' => $user->email, 'course_id' => $course->id]);
    StudentTuition::query()->create([
        'student_id' => $student->id,
        'semester' => 1,
        'school_year' => '2026 - 2027',
        'academic_year' => 1,
        'total_lectures' => 5000,
        'total_laboratory' => 2000,
        'total_tuition' => 7000,
        'total_miscelaneous_fees' => 1000,
        'overall_tuition' => 8000,
        'total_balance' => 6000,
        'discount' => 0,
        'downpayment' => 2000,
        'status' => 'pending',
    ]);

    actingAs($user)->postJson(route('student.tuition.soa.issuances.store'), [
        'school_year' => '2026 - 2027',
        'semester' => 1,
    ])->assertOk();

    $issuance = StatementOfAccountIssuance::query()->sole();
    expect($issuance->student_id)->toBe($student->id)
        ->and($issuance->snapshot['tuition']['overall_tuition'])->toEqual(8000)
        ->and(app(StatementOfAccountService::class)->hasValidIntegrity($issuance))->toBeTrue();
    Bus::assertDispatched(GenerateStudentSoaPdfJob::class, fn (GenerateStudentSoaPdfJob $job): bool => $job->issuanceId === $issuance->id);
});

it('does not allow a student to inspect another students issuance', function (): void {
    GeneralSetting::factory()->create();
    $owner = User::factory()->create(['role' => UserRole::Student->value]);
    $otherUser = User::factory()->create(['role' => UserRole::Student->value]);
    $ownerStudent = Student::factory()->create(['user_id' => $owner->id, 'email' => $owner->email]);
    Student::factory()->create(['user_id' => $otherUser->id, 'email' => $otherUser->email]);
    $snapshot = ['student' => ['name' => 'Owner Student']];
    $issuance = StatementOfAccountIssuance::query()->create([
        'uuid' => fake()->uuid(), 'student_id' => $ownerStudent->id, 'issued_by' => $owner->id,
        'document_number' => 'SOA-SECURITY-001', 'verification_token_hash' => hash('sha256', 'secret'),
        'integrity_signature' => app(StatementOfAccountService::class)->sign($snapshot), 'snapshot' => $snapshot,
        'status' => 'pending', 'issued_at' => now(),
    ]);

    actingAs($otherUser)->getJson(route('student.tuition.soa.issuances.show', $issuance))->assertForbidden();
});

it('publicly verifies masked document details and detects snapshot tampering', function (): void {
    GeneralSetting::factory()->create();
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);
    $token = 'high-entropy-verification-token';
    $snapshot = [
        'student' => ['name' => 'Brent Sin-Ot', 'student_no' => '2065977'],
        'filters' => ['semester' => 1, 'school_year' => '2026 - 2027'],
        'tuition' => ['overall_tuition' => 15325, 'total_paid' => 3000, 'total_balance' => 12325],
        'currency_code' => 'PHP', 'school' => ['name' => 'KoAkademy Sample Campus'],
    ];
    $issuance = StatementOfAccountIssuance::query()->create([
        'uuid' => fake()->uuid(), 'student_id' => $student->id, 'issued_by' => $user->id,
        'document_number' => 'SOA-VERIFY-001', 'verification_token_hash' => hash('sha256', $token),
        'integrity_signature' => app(StatementOfAccountService::class)->sign($snapshot), 'snapshot' => $snapshot,
        'status' => 'ready', 'issued_at' => now(),
    ]);

    $this->get(route('soa.verify', $token))->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('student/tuition/verify-soa')->where('status', 'valid')
        ->where('document.student', 'B**** S*****')->where('document.student_number', '20***77'));

    $issuance->update(['snapshot' => array_replace_recursive($snapshot, ['tuition' => ['total_balance' => 1]])]);

    $this->get(route('soa.verify', $token))->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('status', 'integrity_failed'));
});
