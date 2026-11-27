<?php

declare(strict_types=1);

use App\Enums\StudentStatus;
use App\Enums\StudentType;
use App\Enums\UserRole;
use App\Mail\SignupOtpMail;
use App\Mail\StudentIdentifierRecoveryMail;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\IdentifierGenerator;
use App\Services\StudentConfigurationService;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->school = School::factory()->create(['is_active' => true]);
    app(TenantContext::class)->setCurrentSchool($this->school);
    $this->course = Course::factory()->create(['school_id' => $this->school->id]);
});

it('renders neutral universal signup config without hardcoded LRN references by default', function (): void {
    $this->get(route('signup'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('signup', false)
            ->where('signupConfig.student_id_label', 'Student ID')
            ->where('signupConfig.faculty_id_label', 'Faculty ID')
            ->where('signupConfig.external_identifier_enabled', false)
            ->where('signupConfig.universal_identifier_label', 'Student ID')
            ->where('signupConfig.allow_student_self_signup', true)
            ->where('signupConfig.allow_faculty_self_signup', true)
            ->where('signupConfig.allow_identifier_recovery', true)
            ->where('signupConfig.require_faculty_id', false));
});

it('auto-detects student type and role using only email and universal identifier', function (): void {
    Mail::fake();

    $shsStudent = Student::factory()->create([
        'school_id' => $this->school->id,
        'institution_id' => $this->school->id,
        'email' => 'universal.shs@example.test',
        'student_id' => 'SHS-2026-009',
        'lrn' => null,
        'student_type' => StudentType::SeniorHighSchool,
        'course_id' => $this->course->id,
        'status' => StudentStatus::Enrolled,
    ]);

    // Step 1: Email lookup
    $this->postJson(route('signup.email-lookup'), [
        'email' => 'universal.shs@example.test',
        'include_hints' => true,
    ])
        ->assertOk()
        ->assertJsonPath('found', true)
        ->assertJsonPath('type', 'student')
        ->assertJsonPath('hints.identifier_label', 'Student ID')
        ->assertJsonPath('hints.accepts_external_identifier', false);

    // Step 2: Send OTP without passing student_type or choosing student_id vs lrn
    $this->postJson(route('signup.send-otp'), [
        'email' => 'universal.shs@example.test',
        'user_type' => 'student',
        'identifier' => 'shs-2026-009', // Case-insensitive alphanumeric match
    ])->assertOk();

    Mail::assertSent(SignupOtpMail::class);

    // Step 3: Complete signup without student_type; role is derived from the matched SHS record
    Cache::put('signup_otp_universal.shs@example.test', '654321', 600);

    $this->post(route('signup'), [
        'name' => 'Universal Learner',
        'email' => 'universal.shs@example.test',
        'password' => 'StrongPass123!',
        'password_confirmation' => 'StrongPass123!',
        'user_type' => 'student',
        'identifier' => 'SHS-2026-009',
        'otp' => '654321',
    ])->assertRedirect('/student/dashboard');

    $user = User::query()->where('email', 'universal.shs@example.test')->firstOrFail();

    expect($user->role)->toBe(UserRole::ShsStudent)
        ->and((int) $user->record_id)->toBe($shsStudent->id)
        ->and($shsStudent->refresh()->user_id)->toBe($user->id);
});

it('disambiguates a student email spanning multiple student types automatically by universal identifier', function (): void {
    Mail::fake();

    Student::factory()->create([
        'school_id' => $this->school->id,
        'institution_id' => $this->school->id,
        'email' => 'returning.learner@example.test',
        'student_id' => 'HS-1001',
        'student_type' => StudentType::SeniorHighSchool,
        'course_id' => $this->course->id,
        'status' => StudentStatus::Enrolled,
    ]);

    $collegeRecord = Student::factory()->create([
        'school_id' => $this->school->id,
        'institution_id' => $this->school->id,
        'email' => 'returning.learner@example.test',
        'student_id' => 'COL-2002',
        'student_type' => StudentType::College,
        'course_id' => $this->course->id,
        'status' => StudentStatus::Enrolled,
    ]);

    $this->postJson(route('signup.email-lookup'), [
        'email' => 'returning.learner@example.test',
        'include_hints' => true,
    ])
        ->assertOk()
        ->assertJsonPath('found', true)
        ->assertJsonPath('type', 'student')
        ->assertJsonPath('hints.multiple_types', true);

    $this->postJson(route('signup.send-otp'), [
        'email' => 'returning.learner@example.test',
        'user_type' => 'student',
        'identifier' => 'COL-2002',
    ])->assertOk();

    Cache::put('signup_otp_returning.learner@example.test', '112233', 600);

    $this->post(route('signup'), [
        'name' => 'Returning Learner',
        'email' => 'returning.learner@example.test',
        'password' => 'StrongPass123!',
        'password_confirmation' => 'StrongPass123!',
        'user_type' => 'student',
        'identifier' => 'COL-2002',
        'otp' => '112233',
    ])->assertRedirect('/student/dashboard');

    $user = User::query()->where('email', 'returning.learner@example.test')->firstOrFail();

    expect($user->role)->toBe(UserRole::Student)
        ->and((int) $user->record_id)->toBe($collegeRecord->id);
});

it('supports configurable international regional identifiers like UK ULN and Australia USI on universal signup and recovery', function (): void {
    Mail::fake();

    $presets = StudentConfigurationService::externalIdentifierPresets();
    $ukConfig = StudentConfigurationService::defaults();
    $ukConfig['external_identifier'] = $presets['uk_uln']['config'];
    app(StudentConfigurationService::class)->update($ukConfig, $this->school->id);

    $student = Student::factory()->create([
        'school_id' => $this->school->id,
        'institution_id' => $this->school->id,
        'email' => 'uk.learner@example.test',
        'student_id' => 'UNI-2026-01',
        'lrn' => '9876543210',
        'student_type' => StudentType::College,
        'course_id' => $this->course->id,
        'status' => StudentStatus::Enrolled,
    ]);

    // Email lookup reflects UK ULN dynamically
    $this->postJson(route('signup.email-lookup'), [
        'email' => 'uk.learner@example.test',
        'include_hints' => true,
    ])
        ->assertOk()
        ->assertJsonPath('hints.identifier_label', 'Student ID or ULN')
        ->assertJsonPath('hints.external_identifier_label', 'ULN')
        ->assertJsonPath('hints.accepts_external_identifier', true);

    // Universal signup accepts the 10-digit ULN directly without toggling
    $this->postJson(route('signup.send-otp'), [
        'email' => 'uk.learner@example.test',
        'user_type' => 'student',
        'identifier' => '9876543210',
    ])->assertOk();

    // Identifier recovery uses dynamic ULN labels instead of hardcoded LRN
    $this->postJson(route('signup.recover-identifier'), [
        'email' => 'uk.learner@example.test',
    ])
        ->assertOk()
        ->assertExactJson([
            'message' => 'If a matching student record exists, we have sent your Student ID or ULN to that email address.',
        ]);

    Mail::assertSent(StudentIdentifierRecoveryMail::class, function (StudentIdentifierRecoveryMail $mail): bool {
        $rendered = $mail->render();

        return str_contains($rendered, 'Student ID')
            && str_contains($rendered, 'UNI-2026-01')
            && str_contains($rendered, 'ULN')
            && str_contains($rendered, '9876543210')
            && ! str_contains($rendered, 'LRN');
    });

    // Disabling allow_for_signup rejects the ULN while still accepting the primary Student ID
    $ukConfig['external_identifier']['allow_for_signup'] = false;
    app(StudentConfigurationService::class)->update($ukConfig, $this->school->id);

    $this->postJson(route('signup.send-otp'), [
        'email' => 'uk.learner@example.test',
        'user_type' => 'student',
        'identifier' => '9876543210',
    ])->assertUnprocessable()->assertJsonValidationErrors(['identifier']);

    $this->postJson(route('signup.send-otp'), [
        'email' => 'uk.learner@example.test',
        'user_type' => 'student',
        'identifier' => 'UNI-2026-01',
    ])->assertOk();

    expect($student->refresh()->user_id)->toBeNull();
});

it('persists portal signup settings in System Management and enforces self-signup, recovery, and faculty ID rules', function (): void {
    Mail::fake();

    $admin = User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'school_id' => $this->school->id,
    ]);

    $payload = StudentConfigurationService::defaults();
    $payload['external_identifier'] = StudentConfigurationService::externalIdentifierPresets()['au_usi']['config'];
    $payload['signup'] = [
        'allow_student_self_signup' => false,
        'allow_faculty_self_signup' => true,
        'allow_identifier_recovery' => false,
        'require_faculty_id' => true,
        'student_identifier_placeholder' => 'e.g. 2026-0042 or 3AW88YH9U5',
        'student_identifier_help_text' => 'Check your campus welcome email.',
        'faculty_identifier_placeholder' => 'e.g. EMP-009',
        'faculty_identifier_help_text' => 'Found on your staff badge.',
    ];

    $this->actingAs($admin)
        ->put(route('administrators.system-management.student-configuration.update'), $payload)
        ->assertSessionHasNoErrors();

    auth()->logout();

    $saved = app(StudentConfigurationService::class)->get($this->school->id);
    expect($saved['external_identifier']['label'])->toBe('USI')
        ->and($saved['external_identifier']['format'])->toBe('alphanumeric')
        ->and($saved['signup']['allow_student_self_signup'])->toBeFalse()
        ->and($saved['signup']['allow_identifier_recovery'])->toBeFalse()
        ->and($saved['signup']['require_faculty_id'])->toBeTrue();

    // Student self-signup disabled blocks lookup and recovery email delivery
    Student::factory()->create([
        'school_id' => $this->school->id,
        'institution_id' => $this->school->id,
        'email' => 'blocked.selfsignup@example.test',
        'student_id' => 'STU-999',
        'student_type' => StudentType::College,
        'course_id' => $this->course->id,
        'status' => StudentStatus::Enrolled,
    ]);

    $this->postJson(route('signup.email-lookup'), [
        'email' => 'blocked.selfsignup@example.test',
    ])->assertOk()->assertJsonPath('found', false);

    $this->postJson(route('signup.recover-identifier'), [
        'email' => 'blocked.selfsignup@example.test',
    ])->assertOk();
    Mail::assertNotSent(StudentIdentifierRecoveryMail::class);

    // Faculty signup requires Faculty ID when require_faculty_id is enabled
    Faculty::factory()->create([
        'school_id' => $this->school->id,
        'email' => 'strict.faculty@example.test',
        'faculty_id_number' => 'EMP-777',
    ]);

    $this->postJson(route('signup.email-lookup'), [
        'email' => 'strict.faculty@example.test',
        'include_hints' => true,
    ])
        ->assertOk()
        ->assertJsonPath('found', true)
        ->assertJsonPath('type', 'faculty')
        ->assertJsonPath('hints.requires_faculty_id', true)
        ->assertJsonPath('hints.faculty_id_placeholder', 'e.g. EMP-009');

    $this->postJson(route('signup.send-otp'), [
        'email' => 'strict.faculty@example.test',
        'user_type' => 'faculty',
        'faculty_id_number' => '',
    ])->assertUnprocessable()->assertJsonValidationErrors(['faculty_id_number']);

    $this->postJson(route('signup.send-otp'), [
        'email' => 'strict.faculty@example.test',
        'user_type' => 'faculty',
        'faculty_id_number' => 'EMP-777',
    ])->assertOk();
});

it('uses customized primary display labels from Identifier settings on /signup', function (): void {
    $generator = app(IdentifierGenerator::class);
    $generator->sequenceFor(IdentifierGenerator::Student, schoolId: $this->school->id)
        ->update(['display_label' => 'Matriculation Number']);
    $generator->sequenceFor(IdentifierGenerator::Staff, schoolId: $this->school->id)
        ->update(['display_label' => 'Employee ID']);

    $this->get(route('signup'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('signup', false)
            ->where('signupConfig.student_id_label', 'Matriculation Number')
            ->where('signupConfig.universal_identifier_label', 'Matriculation Number')
            ->where('signupConfig.faculty_id_label', 'Employee ID'));
});
