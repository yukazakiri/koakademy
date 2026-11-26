<?php

declare(strict_types=1);

use App\Enums\StudentStatus;
use App\Enums\StudentType;
use App\Enums\UserRole;
use App\Mail\StudentIdentifierRecoveryMail;
use App\Models\Course;
use App\Models\DocumentLocation;
use App\Models\Faculty;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Models\Subject;
use App\Models\User;
use App\Notifications\ApplicantApprovedForRequirements;
use App\Notifications\ApplicantSubmissionReceiptNotification;
use App\Notifications\PortalAccountWelcomeNotification;
use App\Services\StudentConfigurationService;
use App\Services\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    GeneralSetting::factory()->create([
        'semester' => 1,
        'school_starting_date' => '2025-08-01',
        'school_ending_date' => '2026-05-31',
    ]);
});

it('allows an authenticated student to view, upload, and download their own registrar documents', function (): void {
    Storage::fake('private');

    $course = Course::factory()->create();
    $user = User::factory()->create([
        'role' => UserRole::Student,
        'email' => 'vault.student@example.test',
    ]);
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'email' => 'vault.student@example.test',
        'student_id' => '20261001',
        'course_id' => $course->id,
        'document_location_id' => null,
        'status' => StudentStatus::Enrolled,
    ]);

    $this->actingAs($user)
        ->get(route('student.documents.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('student/documents/index', false)
            ->where('summary.uploaded', 0)
            ->where('summary.total', 4)
            ->has('documents', 4));

    $file = UploadedFile::fake()->create('birth_cert.pdf', 120, 'application/pdf');

    $this->actingAs($user)
        ->post(route('student.documents.store'), [
            'document_type' => 'birth_certificate',
            'file' => $file,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $student->refresh()->load('DocumentLocation');
    expect($student->DocumentLocation)->not->toBeNull();
    $storedPath = $student->DocumentLocation->birth_certificate;
    expect($storedPath)->toBeString()->not->toBe('');
    Storage::disk('private')->assertExists($storedPath);

    $this->actingAs($user)
        ->get(route('student.documents.download', ['documentType' => 'birth_certificate']))
        ->assertOk();

    // Another student cannot download the first student's document.
    $otherUser = User::factory()->create([
        'role' => UserRole::Student,
        'email' => 'other.student@example.test',
    ]);
    Student::factory()->create([
        'user_id' => $otherUser->id,
        'email' => 'other.student@example.test',
        'student_id' => '20261002',
        'course_id' => $course->id,
        'document_location_id' => null,
        'status' => StudentStatus::Enrolled,
    ]);

    $this->actingAs($otherUser)
        ->get(route('student.documents.download', ['documentType' => 'birth_certificate']))
        ->assertNotFound();

    // Validation rejects disallowed types.
    $this->actingAs($user)
        ->post(route('student.documents.store'), [
            'document_type' => 'invalid_slot',
            'file' => UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf'),
        ])
        ->assertSessionHasErrors(['document_type']);
});

it('emails student identifier recovery for unclaimed students and returns privacy-safe responses otherwise', function (): void {
    Mail::fake();

    $course = Course::factory()->create();
    Student::factory()->create([
        'user_id' => null,
        'email' => 'Unclaimed.Student@Example.Test',
        'student_id' => '20269901',
        'lrn' => '123456789012',
        'student_type' => StudentType::College,
        'course_id' => $course->id,
        'status' => StudentStatus::Enrolled,
    ]);

    $expectedMessage = 'If a matching student record exists, we have sent your Student Number / LRN to that email address.';

    $this->postJson(route('signup.recover-identifier'), [
        'email' => 'unclaimed.student@example.test',
    ])
        ->assertOk()
        ->assertExactJson(['message' => $expectedMessage]);

    Mail::assertSent(StudentIdentifierRecoveryMail::class, function (StudentIdentifierRecoveryMail $mail): bool {
        $rendered = $mail->render();

        return $mail->hasTo('unclaimed.student@example.test')
            && str_contains($rendered, '20269901')
            && str_contains($rendered, '123456789012')
            && str_contains($rendered, '/signup?email=unclaimed.student%40example.test');
    });

    // Unknown email returns identical 200 JSON and sends no email.
    Mail::fake();
    $this->postJson(route('signup.recover-identifier'), [
        'email' => 'nobody@example.test',
    ])
        ->assertOk()
        ->assertExactJson(['message' => $expectedMessage]);
    Mail::assertNothingSent();

    // Email with an existing User account returns identical 200 JSON and sends no email.
    User::factory()->create(['email' => 'claimed.student@example.test']);
    Student::factory()->create([
        'user_id' => null,
        'email' => 'claimed.student@example.test',
        'student_id' => '20269902',
        'course_id' => $course->id,
        'status' => StudentStatus::Enrolled,
    ]);

    Mail::fake();
    $this->postJson(route('signup.recover-identifier'), [
        'email' => 'claimed.student@example.test',
    ])
        ->assertOk()
        ->assertExactJson(['message' => $expectedMessage]);
    Mail::assertNothingSent();
});

it('verifies email and sends branded welcome notification when provisioning a faculty portal account', function (): void {
    Notification::fake();

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $faculty = Faculty::factory()->create([
        'email' => 'prof.welcome@example.test',
        'faculty_id_number' => 'FAC-777',
    ]);

    $this->actingAs($admin)
        ->post(route('administrators.faculties.portal-account', $faculty), [
            'mode' => 'create',
            'role' => UserRole::Instructor->value,
            'send_reset_link' => true,
        ])
        ->assertRedirect();

    $portalUser = User::query()->where('email', 'prof.welcome@example.test')->firstOrFail();

    expect($portalUser->email_verified_at)->not->toBeNull()
        ->and($portalUser->faculty_id_number)->toBe('FAC-777');

    Notification::assertSentTo($portalUser, PortalAccountWelcomeNotification::class, function (PortalAccountWelcomeNotification $notification) use ($portalUser): bool {
        $mail = $notification->toMail($portalUser);
        $renderedLines = implode("\n", array_merge($mail->introLines, $mail->outroLines));

        return str_contains($renderedLines, 'prof.welcome@example.test')
            && str_contains($renderedLines, 'FAC-777')
            && str_contains((string) $mail->actionUrl, 'reset-password');
    });
});

it('bulk provisions faculty portal accounts while skipping admin, student, and other-faculty conflicts', function (): void {
    Notification::fake();

    $eligible = Faculty::factory()->create([
        'email' => 'bulk.new@example.test',
        'faculty_id_number' => 'FAC-BULK-1',
    ]);

    $alreadyLinked = Faculty::factory()->create([
        'email' => 'bulk.linked@example.test',
        'faculty_id_number' => 'FAC-BULK-2',
    ]);
    User::factory()->create([
        'email' => 'bulk.linked@example.test',
        'role' => UserRole::Instructor,
        'faculty_id_number' => 'FAC-BULK-2',
        'record_id' => $alreadyLinked->id,
    ]);

    $adminConflict = Faculty::factory()->create([
        'email' => 'bulk.admin@example.test',
        'faculty_id_number' => 'FAC-BULK-3',
    ]);
    $adminUser = User::factory()->create([
        'email' => 'bulk.admin@example.test',
        'role' => UserRole::Admin,
    ]);

    $studentConflict = Faculty::factory()->create([
        'email' => 'bulk.student@example.test',
        'faculty_id_number' => 'FAC-BULK-4',
    ]);
    $studentUser = User::factory()->create([
        'email' => 'bulk.student@example.test',
        'role' => UserRole::Student,
    ]);

    $otherFaculty = Faculty::factory()->create([
        'email' => 'other.owner@example.test',
        'faculty_id_number' => 'FAC-OTHER',
    ]);
    $otherFacultyConflict = Faculty::factory()->create([
        'email' => 'bulk.otherfac@example.test',
        'faculty_id_number' => 'FAC-BULK-5',
    ]);
    $otherFacultyUser = User::factory()->create([
        'email' => 'bulk.otherfac@example.test',
        'role' => UserRole::Instructor,
        'record_id' => $otherFaculty->id,
    ]);

    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)
        ->postJson(portalUrlForAdministrators(route('administrators.faculties.bulk.portal-accounts', [], false)), [
            'faculty_ids' => [
                $eligible->id,
                $alreadyLinked->id,
                $adminConflict->id,
                $studentConflict->id,
                $otherFacultyConflict->id,
            ],
            'send_activation_email' => true,
        ])
        ->assertOk();

    $payload = $response->json();

    expect($payload['provisioned'])->toBe(1)
        ->and($payload['already_linked'])->toBe(1)
        ->and($payload['skipped'])->toBe(3);

    $provisionedUser = User::query()->where('email', 'bulk.new@example.test')->firstOrFail();
    expect($provisionedUser->email_verified_at)->not->toBeNull()
        ->and((string) $provisionedUser->record_id)->toBe((string) $eligible->id);

    Notification::assertSentTo($provisionedUser, PortalAccountWelcomeNotification::class);
    expect($adminUser->refresh()->role)->toBe(UserRole::Admin)
        ->and($studentUser->refresh()->role)->toBe(UserRole::Student)
        ->and((string) $otherFacultyUser->refresh()->record_id)->toBe((string) $otherFaculty->id);
});

it('sends submission receipt, syncs uploaded applicant documents, and powers the public status tracker', function (): void {
    Notification::fake();
    Storage::fake('private');

    app(StudentConfigurationService::class)->update(StudentConfigurationService::defaults(legacy: true));
    $school = School::factory()->create(['name' => 'KoAkademy']);
    app(StudentConfigurationService::class)->update(StudentConfigurationService::defaults(legacy: true), $school->id);
    app(TenantContext::class)->setCurrentSchool($school);

    $course = Course::factory()->create([
        'department' => 'TESDA',
        'is_active' => true,
    ]);

    $response = $this->post(route('enrollment.store'), [
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'student_type' => 'tesda',
        'course_id' => $course->id,
        'birth_date' => '2001-05-15',
        'gender' => 'female',
        'nationality' => 'Filipino',
        'address' => '456 Campus Ave',
        'email' => 'maria.santos@example.test',
        'phone' => '09171234567',
        'civil_status' => 'Single',
        'income_bracket_mode' => 'annual',
        'use_same_parent_income' => true,
        'family_income_bracket' => 'below_250k',
        'contacts' => [
            'personal_contact' => '09171234567',
            'emergency_contact_name' => 'Elena Santos',
            'emergency_contact_phone' => '09181234567',
            'emergency_contact_relationship' => 'Mother',
        ],
        'parents' => [
            'guardian_name' => 'Elena Santos',
            'guardian_relationship' => 'Mother',
            'guardian_contact' => '09181234567',
        ],
        'documents' => [
            [
                'type' => 'psa_birth_certificate',
                'file' => UploadedFile::fake()->create('psa.pdf', 100, 'application/pdf'),
            ],
            [
                'type' => 'profile_photo',
                'file' => UploadedFile::fake()->image('id-photo.jpg'),
            ],
        ],
        'consent' => true,
    ]);

    $response->assertRedirect(route('enrollment.create'));

    $student = Student::query()->with('DocumentLocation')->where('email', 'maria.santos@example.test')->firstOrFail();

    expect($student->DocumentLocation)->not->toBeNull()
        ->and($student->DocumentLocation->birth_certificate)->not->toBeNull()
        ->and($student->DocumentLocation->picture_1x1)->not->toBeNull();

    Notification::assertSentTo($student, ApplicantSubmissionReceiptNotification::class, function (ApplicantSubmissionReceiptNotification $notification) use ($student): bool {
        $mail = $notification->toMail($student);

        return $notification->applicantId === (string) $student->student_id
            && str_contains((string) $mail->actionUrl, '/enrollment/status');
    });

    // Status page renders with prefill props.
    $this->get(route('enrollment.status', [
        'email' => 'maria.santos@example.test',
        'reference_id' => (string) $student->student_id,
    ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('enrollment/status', false)
            ->where('prefill.email', 'maria.santos@example.test')
            ->where('prefill.reference_id', (string) $student->student_id));

    // Unknown lookup returns 404.
    $this->postJson(route('enrollment.status.check'), [
        'email' => 'maria.santos@example.test',
        'reference_id' => 'WRONG-ID',
    ])->assertNotFound();

    // Valid applicant status returns Under Review timeline.
    $this->postJson(route('enrollment.status.check'), [
        'email' => 'maria.santos@example.test',
        'reference_id' => (string) $student->student_id,
    ])
        ->assertOk()
        ->assertJsonPath('found', true)
        ->assertJsonPath('application.reference_id', (string) $student->student_id)
        ->assertJsonPath('application.documents_on_file', 2);

    // Once enrolled, status tracker exposes pre-filled /signup?email=... link.
    $student->update(['status' => StudentStatus::Enrolled]);

    $this->postJson(route('enrollment.status.check'), [
        'email' => 'maria.santos@example.test',
        'reference_id' => (string) $student->student_id,
    ])
        ->assertOk()
        ->assertJsonPath('application.can_activate_portal', true)
        ->assertJsonPath('application.signup_url', url('/signup?email='.urlencode('maria.santos@example.test')));
});

it('includes student_id and prefilled portal activation CTA in applicant approval email', function (): void {
    $course = Course::factory()->create(['code' => 'BSIT']);
    $student = Student::factory()->create([
        'student_id' => '20265544',
        'email' => 'approved.applicant@example.test',
        'course_id' => $course->id,
        'status' => StudentStatus::Applicant,
    ]);

    $notification = new ApplicantApprovedForRequirements(
        senderName: 'Registrar Office',
        senderRole: 'Registrar',
    );
    $mailMessage = $notification->toMail($student);
    $rendered = (string) $mailMessage->render();

    expect($rendered)
        ->toContain('20265544')
        ->toContain('Activate Your Student Portal')
        ->toContain('/signup?email=approved.applicant%40example.test');
});

it('creates StudentTuition and syncs contacts documents to DocumentLocation on bulk applicant conversion', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::factory()->create([
        'lec_per_unit' => 500,
        'lab_per_unit' => 750,
    ]);
    Subject::factory()->create([
        'course_id' => $course->id,
        'academic_year' => 1,
        'semester' => 1,
        'lecture' => 3,
        'laboratory' => 0,
    ]);

    $applicant = Student::factory()->create([
        'course_id' => $course->id,
        'academic_year' => 1,
        'status' => StudentStatus::Applicant,
        'document_location_id' => null,
        'contacts' => [
            'documents' => [
                ['type' => 'birth_certificate', 'path' => 'enrollment-documents/20260001/birth.pdf'],
                ['type' => 'form_138', 'path' => 'enrollment-documents/20260001/form138.pdf'],
                ['type' => 'good_moral', 'path' => 'enrollment-documents/20260001/good_moral.pdf'],
                ['type' => 'photo', 'path' => 'enrollment-documents/20260001/photo.jpg'],
            ],
        ],
    ]);

    $this->actingAs($admin)
        ->postJson(route('administrators.enrollments.applicants.bulk-action'), [
            'action' => 'convert_to_enrollment',
            'ids' => [$applicant->id],
        ])
        ->assertOk()
        ->assertJsonPath('processed', 1);

    $enrollment = StudentEnrollment::query()->where('student_id', $applicant->id)->firstOrFail();
    expect(StudentTuition::query()->where('enrollment_id', $enrollment->id)->exists())->toBeTrue();

    $applicant->refresh()->load('DocumentLocation');
    expect($applicant->DocumentLocation)->toBeInstanceOf(DocumentLocation::class)
        ->and($applicant->DocumentLocation->birth_certificate)->toBe('enrollment-documents/20260001/birth.pdf')
        ->and($applicant->DocumentLocation->form_138)->toBe('enrollment-documents/20260001/form138.pdf')
        ->and($applicant->DocumentLocation->good_moral_cert)->toBe('enrollment-documents/20260001/good_moral.pdf')
        ->and($applicant->DocumentLocation->picture_1x1)->toBe('enrollment-documents/20260001/photo.jpg');
});
