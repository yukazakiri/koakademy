<?php

declare(strict_types=1);

use App\Enums\StudentType;
use App\Enums\UserRole;
use App\Mail\SignupOtpMail;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

// uses(\Illuminate\Foundation\Testing\RefreshDatabase::class); // Managed globally

beforeEach(function () {
    $this->course = Course::factory()->create();
});

it('can lookup existing student email', function () {
    $student = Student::factory()->create([
        'email' => 'student@test.com',
        'student_type' => StudentType::College,
        'course_id' => $this->course->id,
        'document_location_id' => null,
        'status' => 'enrolled',
    ]);

    $response = $this->postJson(route('signup.email-lookup'), [
        'email' => 'student@test.com',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'found' => true,
            'type' => 'student',
            'record_id' => $student->id,
        ])
        ->assertJsonMissing(['student_id', 'lrn']);
});

it('can lookup existing faculty email', function () {
    $faculty = Faculty::factory()->create([
        'email' => 'Faculty@Test.com',
    ]);

    $response = $this->postJson(route('signup.email-lookup'), [
        'email' => 'faculty@test.com',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'found' => true,
            'type' => 'faculty',
            'record_id' => $faculty->id,
        ]);
});

it('returns error for unknown email', function () {
    $response = $this->postJson(route('signup.email-lookup'), [
        'email' => 'unknown@test.com',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'found' => false,
            'message' => 'Email not found in our records. Please use your registered school email.',
        ]);
});

it('returns error for existing user account', function () {
    User::factory()->create([
        'email' => 'Existing@Test.com',
    ]);

    $response = $this->postJson(route('signup.email-lookup'), [
        'email' => 'existing@test.com',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'found' => false,
            'account_exists' => true,
        ]);
});

it('handles mixed-case records in the API signup lookup', function () {
    User::factory()->create(['email' => 'Api.Existing@Test.com']);
    $faculty = Faculty::factory()->create(['email' => 'Api.Faculty@Test.com']);

    $this->postJson('/api/v1/auth/signup/email-lookup', [
        'email' => 'api.existing@test.com',
    ])->assertOk()->assertJson([
        'found' => false,
        'account_exists' => true,
    ]);

    $this->postJson('/api/v1/auth/signup/email-lookup', [
        'email' => 'api.faculty@test.com',
    ])->assertOk()->assertJson([
        'found' => true,
        'type' => 'faculty',
        'record_id' => $faculty->id,
    ]);
});

it('validates signup uniqueness using the normalized email', function () {
    User::factory()->create(['email' => 'existing.student@test.com']);

    $payload = [
        'name' => 'Duplicate Student',
        'email' => 'Existing.Student@Test.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'user_type' => 'student',
        'student_type' => 'college',
        'student_id' => '1234567',
        'otp' => 'ABC123',
    ];

    $this->post(route('signup'), $payload)
        ->assertSessionHasErrors(['email']);

    $this->postJson('/api/v1/auth/signup', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('verifies mixed-case faculty records before sending signup otp', function () {
    Mail::fake();

    $faculty = Faculty::factory()->create([
        'email' => 'Otp.Faculty@Test.com',
        'faculty_id_number' => 'FAC-CASE-1',
    ]);
    $payload = [
        'email' => 'otp.faculty@test.com',
        'user_type' => 'faculty',
        'role' => 'instructor',
        'faculty_id_number' => 'FAC-CASE-1',
    ];

    $this->postJson(route('signup.send-otp'), $payload)->assertOk();
    $this->postJson('/api/v1/auth/signup/send-otp', $payload)->assertOk();

    Mail::assertSent(SignupOtpMail::class, 2);
    Mail::assertSent(SignupOtpMail::class, fn (SignupOtpMail $mail): bool => $mail->hasTo(mb_strtolower($faculty->email)));
});

it('matches mixed-case faculty records during web and API signup', function () {
    $webFaculty = Faculty::factory()->create([
        'email' => 'Web.Faculty@Test.com',
        'faculty_id_number' => 'FAC-WEB-1',
    ]);
    Cache::put('signup_otp_web.faculty@test.com', 'WEBOTP', 600);

    $this->post(route('signup'), [
        'name' => 'Web Faculty',
        'email' => 'web.faculty@test.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'instructor',
        'faculty_id_number' => 'FAC-WEB-1',
        'otp' => 'WEBOTP',
    ])->assertRedirect('/faculty/dashboard');

    $webUser = User::query()->where('email', 'web.faculty@test.com')->firstOrFail();
    expect($webUser->record_id)->toBe((string) $webFaculty->id);

    $apiFaculty = Faculty::factory()->create([
        'email' => 'Api.Signup.Faculty@Test.com',
        'faculty_id_number' => 'FAC-API-1',
    ]);
    Cache::put('signup_otp_api.signup.faculty@test.com', 'APIOTP', 600);

    $this->postJson('/api/v1/auth/signup', [
        'name' => 'API Faculty',
        'email' => 'api.signup.faculty@test.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'instructor',
        'faculty_id_number' => 'FAC-API-1',
        'otp' => 'APIOTP',
    ])->assertCreated();

    $apiUser = User::query()->where('email', 'api.signup.faculty@test.com')->firstOrFail();
    expect($apiUser->record_id)->toBe((string) $apiFaculty->id);
});

it('can send otp with valid student credentials', function () {
    Mail::fake();

    $student = Student::factory()->create([
        'email' => 'student@test.com',
        'student_id' => '123456',
        'student_type' => StudentType::College,
        'course_id' => $this->course->id,
        'document_location_id' => null,
        'status' => 'enrolled',
    ]);

    $response = $this->postJson(route('signup.send-otp'), [
        'email' => 'student@test.com',
        'user_type' => 'student',
        'student_type' => 'college',
        'student_id' => '123456',
        'record_id' => $student->id,
    ]);

    $response->assertStatus(200);

    Mail::assertSent(SignupOtpMail::class, function ($mail) use ($student) {
        return $mail->hasTo($student->email);
    });
});

it('fails to send otp with mismatched student id', function () {
    $student = Student::factory()->create([
        'email' => 'student@test.com',
        'student_id' => '123456',
        'student_type' => StudentType::College,
        'course_id' => $this->course->id,
        'document_location_id' => null,
        'status' => 'enrolled',
    ]);

    $response = $this->postJson(route('signup.send-otp'), [
        'email' => 'student@test.com',
        'user_type' => 'student',
        'student_type' => 'college',
        'student_id' => '999999', // Wrong ID
        'record_id' => $student->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['student_id']);
});

it('can signup with valid otp', function () {
    $student = Student::factory()->create([
        'email' => 'student@test.com',
        'student_id' => '123456',
        'student_type' => StudentType::College,
        'course_id' => $this->course->id,
        'document_location_id' => null,
        'status' => 'enrolled',
    ]);

    $otp = '123456';
    Cache::put('signup_otp_student@test.com', $otp, 600);

    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);

    $response = $this->post(route('signup'), [
        'name' => 'Test Student',
        'email' => 'student@test.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'user_type' => 'student',
        'student_type' => 'college',
        'student_id' => '123456',
        'record_id' => $student->id,
        'otp' => $otp,
    ]);

    $response->assertRedirect('/student/dashboard');
    $this->assertAuthenticated();

    $this->assertDatabaseHas('users', [
        'email' => 'student@test.com',
    ]);

    $user = User::where('email', 'student@test.com')->first();
    expect($user->email_verified_at)->not->toBeNull();
});

it('fails signup with invalid otp', function () {
    $student = Student::factory()->create([
        'email' => 'student@test.com',
        'student_id' => '123456',
        'student_type' => StudentType::College,
        'course_id' => $this->course->id,
        'document_location_id' => null,
        'status' => 'enrolled',
    ]);

    $otp = '123456';
    Cache::put('signup_otp_student@test.com', $otp, 600);

    $response = $this->post(route('signup'), [
        'name' => 'Test Student',
        'email' => 'student@test.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'user_type' => 'student',
        'student_type' => 'college',
        'student_id' => '123456',
        'record_id' => $student->id,
        'otp' => 'WRONG',
    ]);

    $response->assertSessionHasErrors(['otp']);
    $this->assertGuest();
});

it('assigns a new student to the organization that owns the matched record', function () {
    $defaultSchool = School::factory()->create();
    $branchSchool = School::factory()->create();
    app(TenantContext::class)->setCurrentSchool($defaultSchool);

    $student = Student::factory()->create([
        'institution_id' => $branchSchool->id,
        'school_id' => $branchSchool->id,
        'email' => 'branch.student@test.com',
        'student_id' => 7654321,
        'student_type' => StudentType::College,
        'course_id' => $this->course->id,
    ]);

    Cache::put('signup_otp_branch.student@test.com', 'BRANCH', 600);

    $this->post(route('signup'), [
        'name' => 'Branch Student',
        'email' => 'Branch.Student@Test.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'user_type' => 'student',
        'student_type' => 'college',
        'student_id' => '7654321',
        'record_id' => $student->id,
        'otp' => 'BRANCH',
    ])->assertRedirect('/student/dashboard');

    $user = User::query()->where('email', 'branch.student@test.com')->firstOrFail();

    expect($user->school_id)->toBe($branchSchool->id)
        ->and($student->refresh()->user_id)->toBe($user->id);

    $this->assertDatabaseHas('organization_user', [
        'user_id' => $user->id,
        'school_id' => $branchSchool->id,
        'role' => 'student',
        'is_primary' => true,
        'is_active' => true,
    ]);
    $this->assertDatabaseMissing('organization_user', [
        'user_id' => $user->id,
        'school_id' => $defaultSchool->id,
    ]);
});

it('uses student id to disambiguate the same email across organizations', function () {
    Mail::fake();

    $mainSchool = School::factory()->create();
    $branchSchool = School::factory()->create();
    app(TenantContext::class)->setCurrentSchool($mainSchool);

    Student::factory()->create([
        'institution_id' => $mainSchool->id,
        'school_id' => $mainSchool->id,
        'email' => 'shared.student@test.com',
        'student_id' => 1111111,
        'student_type' => StudentType::College,
        'course_id' => $this->course->id,
    ]);

    $branchStudent = Student::factory()->create([
        'institution_id' => $branchSchool->id,
        'school_id' => $branchSchool->id,
        'email' => 'shared.student@test.com',
        'student_id' => 2222222,
        'student_type' => StudentType::College,
        'course_id' => $this->course->id,
    ]);

    $this->postJson(route('signup.email-lookup'), [
        'email' => 'shared.student@test.com',
    ])->assertOk()->assertJson([
        'found' => true,
        'type' => 'student',
        'record_id' => null,
    ]);

    $this->postJson(route('signup.send-otp'), [
        'email' => 'shared.student@test.com',
        'user_type' => 'student',
        'student_type' => 'college',
        'student_id' => '2222222',
        'record_id' => null,
    ])->assertOk();

    Mail::assertSent(SignupOtpMail::class, fn (SignupOtpMail $mail): bool => $mail->hasTo('shared.student@test.com'));

    Cache::put('signup_otp_shared.student@test.com', 'BRANCH', 600);

    $this->post(route('signup'), [
        'name' => 'Shared Branch Student',
        'email' => 'shared.student@test.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'user_type' => 'student',
        'student_type' => 'college',
        'student_id' => '2222222',
        'record_id' => null,
        'otp' => 'BRANCH',
    ])->assertRedirect('/student/dashboard');

    $user = User::query()->where('email', 'shared.student@test.com')->firstOrFail();

    expect($user->school_id)->toBe($branchSchool->id)
        ->and((int) $user->record_id)->toBe($branchStudent->id);
});

it('rejects a student record whose email does not match the registrant', function () {
    $school = School::factory()->create();
    $student = Student::factory()->create([
        'institution_id' => $school->id,
        'school_id' => $school->id,
        'email' => 'real.student@test.com',
        'student_id' => 3333333,
        'student_type' => StudentType::College,
        'course_id' => $this->course->id,
    ]);

    Cache::put('signup_otp_attacker@test.com', 'ATTACK', 600);

    $this->post(route('signup'), [
        'name' => 'Wrong Student',
        'email' => 'attacker@test.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'user_type' => 'student',
        'student_type' => 'college',
        'student_id' => '3333333',
        'record_id' => $student->id,
        'otp' => 'ATTACK',
    ])->assertSessionHasErrors(['student_id']);

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'attacker@test.com']);
    expect($student->refresh()->user_id)->toBeNull();
});

it('assigns API student signup to the matched organization', function () {
    $defaultSchool = School::factory()->create();
    $branchSchool = School::factory()->create();
    app(TenantContext::class)->setCurrentSchool($defaultSchool);

    $student = Student::factory()->create([
        'institution_id' => $branchSchool->id,
        'school_id' => $branchSchool->id,
        'email' => 'api.branch.student@test.com',
        'student_id' => 8888888,
        'student_type' => StudentType::College,
        'course_id' => $this->course->id,
    ]);
    Cache::put('signup_otp_api.branch.student@test.com', 'APIOTP', 600);

    $this->postJson('/api/v1/auth/signup', [
        'name' => 'API Branch Student',
        'email' => 'api.branch.student@test.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'user_type' => 'student',
        'student_type' => 'college',
        'student_id' => '8888888',
        'record_id' => $student->id,
        'otp' => 'APIOTP',
    ])->assertCreated()
        ->assertJsonPath('data.user.email', 'api.branch.student@test.com')
        ->assertJsonPath('data.token_type', 'Bearer');

    $user = User::query()->where('email', 'api.branch.student@test.com')->firstOrFail();

    expect($user->school_id)->toBe($branchSchool->id)
        ->and($student->refresh()->user_id)->toBe($user->id);
    $this->assertDatabaseHas('organization_user', [
        'user_id' => $user->id,
        'school_id' => $branchSchool->id,
        'is_primary' => true,
        'is_active' => true,
    ]);
});

it('backfills organization assignments for previously linked student users', function () {
    $school = School::factory()->create();
    $user = User::factory()->create([
        'email' => 'legacy.student@test.com',
        'role' => UserRole::Student,
        'school_id' => null,
        'record_id' => 'stale-record',
    ]);
    $student = Student::factory()->create([
        'institution_id' => $school->id,
        'school_id' => $school->id,
        'email' => 'legacy.student@test.com',
        'student_id' => 9999999,
        'student_type' => StudentType::College,
        'course_id' => $this->course->id,
        'user_id' => $user->id,
    ]);

    $migration = require database_path('migrations/2026_08_26_084009_backfill_student_organization_assignments.php');
    $migration->up();

    expect($user->refresh()->school_id)->toBe($school->id)
        ->and((int) $user->record_id)->toBe($student->id);
    $this->assertDatabaseHas('organization_user', [
        'user_id' => $user->id,
        'school_id' => $school->id,
        'role' => 'student',
        'is_primary' => true,
        'is_active' => true,
    ]);
});

it('does not backfill student users from inactive organizations', function () {
    $inactiveSchool = School::factory()->inactive()->create();
    $user = User::factory()->create([
        'email' => 'inactive.student@test.com',
        'role' => UserRole::Student,
        'school_id' => null,
        'record_id' => null,
    ]);
    Student::factory()->create([
        'institution_id' => $inactiveSchool->id,
        'school_id' => $inactiveSchool->id,
        'email' => 'inactive.student@test.com',
        'student_id' => 2020202,
        'student_type' => StudentType::College,
        'course_id' => $this->course->id,
        'user_id' => $user->id,
    ]);

    $migration = require database_path('migrations/2026_08_26_084009_backfill_student_organization_assignments.php');
    $migration->up();

    expect($user->refresh()->school_id)->toBeNull()
        ->and($user->record_id)->toBeNull();
    $this->assertDatabaseMissing('organization_user', [
        'user_id' => $user->id,
        'school_id' => $inactiveSchool->id,
    ]);
});

it('preserves record id when multiple linked students make identity ambiguous', function () {
    $school = School::factory()->create();
    $user = User::factory()->create([
        'email' => 'ambiguous.legacy.student@test.com',
        'role' => UserRole::Student,
        'school_id' => null,
        'record_id' => 'existing-record',
    ]);
    Student::factory()->count(2)->create([
        'institution_id' => $school->id,
        'school_id' => $school->id,
        'email' => 'ambiguous.legacy.student@test.com',
        'student_type' => StudentType::College,
        'course_id' => $this->course->id,
        'user_id' => $user->id,
    ]);

    $migration = require database_path('migrations/2026_08_26_084009_backfill_student_organization_assignments.php');
    $migration->up();

    expect($user->refresh()->school_id)->toBe($school->id)
        ->and($user->record_id)->toBe('existing-record');
    $this->assertDatabaseHas('organization_user', [
        'user_id' => $user->id,
        'school_id' => $school->id,
        'is_primary' => true,
        'is_active' => true,
    ]);
});

it('rolls back a legacy student assignment when membership creation fails', function () {
    $school = School::factory()->create();
    $user = User::factory()->create([
        'email' => 'retry.student@test.com',
        'role' => UserRole::Student,
        'school_id' => null,
    ]);
    Student::factory()->create([
        'institution_id' => $school->id,
        'school_id' => $school->id,
        'email' => 'retry.student@test.com',
        'student_id' => 1010101,
        'student_type' => StudentType::College,
        'course_id' => $this->course->id,
        'user_id' => $user->id,
    ]);

    $failMembershipInsert = true;
    DB::listen(function (QueryExecuted $query) use (&$failMembershipInsert): void {
        if ($failMembershipInsert
            && str_contains(mb_strtolower($query->sql), 'insert into')
            && str_contains(mb_strtolower($query->sql), 'organization_user')) {
            $failMembershipInsert = false;

            throw new RuntimeException('Forced membership insert failure.');
        }
    });

    $migration = require database_path('migrations/2026_08_26_084009_backfill_student_organization_assignments.php');

    expect(fn () => $migration->up())
        ->toThrow(RuntimeException::class, 'Forced membership insert failure.');

    expect($user->refresh()->school_id)->toBeNull()
        ->and($user->record_id)->toBeNull();
    $this->assertDatabaseMissing('organization_user', [
        'user_id' => $user->id,
        'school_id' => $school->id,
    ]);

    $migration->up();

    expect($user->refresh()->school_id)->toBe($school->id);
    $this->assertDatabaseHas('organization_user', [
        'user_id' => $user->id,
        'school_id' => $school->id,
        'is_primary' => true,
        'is_active' => true,
    ]);
});
