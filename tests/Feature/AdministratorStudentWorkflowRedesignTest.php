<?php

declare(strict_types=1);

use App\Enrollment\EnrollmentRequirementService;
use App\Enums\StudentType;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\DocumentLocation;
use App\Models\EnrollmentRequirement;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\ShsStrand;
use App\Models\Student;
use App\Models\StudentDocumentRecord;
use App\Models\StudentEnrollment;
use App\Models\StudentHold;
use App\Models\Subject;
use App\Models\User;
use App\Notifications\StudentDocumentStatusNotification;
use App\Services\IdentifierGenerator;
use App\Services\StudentConfigurationService;
use App\Services\StudentDocumentService;
use App\Services\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\withoutVite;

beforeEach(function (): void {
    withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
});

it('blocks unauthorized staff roles from student directory, documents hub, and bulk mutations', function (): void {
    $school = School::factory()->create();
    $guard = User::factory()->create([
        'role' => UserRole::SecurityGuard,
        'school_id' => $school->id,
    ]);
    $student = Student::factory()->create(['school_id' => $school->id]);

    actingAs($guard)
        ->get(route('administrators.students.index'))
        ->assertForbidden();

    actingAs($guard)
        ->get(route('administrators.students.documents.list'))
        ->assertForbidden();

    actingAs($guard)
        ->delete(route('administrators.students.bulk-destroy'), ['student_ids' => [$student->id]])
        ->assertForbidden();

    actingAs($guard)
        ->delete(route('administrators.students.bulk-force-destroy'), [
            'student_ids' => [$student->id],
            'confirm_text' => 'PERMANENTLY DELETE 1 STUDENT',
        ])
        ->assertForbidden();

    actingAs($guard)
        ->patch(route('administrators.students.bulk-update-status'), [
            'student_ids' => [$student->id],
            'status' => 'enrolled',
        ])
        ->assertForbidden();

    actingAs($guard)
        ->post(route('administrators.students.bulk-email'), [
            'student_ids' => [$student->id],
            'subject' => 'Notice',
            'body' => 'Body text',
        ])
        ->assertForbidden();
});

it('hides signature_path from student serialization', function (): void {
    $student = Student::factory()->create([
        'signature_path' => 'students/1/signatures/secret.png',
    ]);

    expect($student->toArray())->not->toHaveKey('signature_path');
});

it('validates changeCourse against the subject table without SQL errors', function (): void {
    $school = School::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);
    $oldCourse = Course::factory()->create(['school_id' => $school->id]);
    $newCourse = Course::factory()->create(['school_id' => $school->id]);
    $subject = Subject::factory()->create(['course_id' => $oldCourse->id]);
    $student = Student::factory()->create([
        'school_id' => $school->id,
        'course_id' => $oldCourse->id,
    ]);

    actingAs($admin)
        ->patch(route('administrators.students.change-course', $student), [
            'course_id' => $newCourse->id,
            'credited_subject_ids' => [$subject->id],
        ])
        ->assertRedirect();

    expect($student->fresh()->course_id)->toBe($newCourse->id);
});

it('cleans up documents and holds during bulk force delete', function (): void {
    $school = School::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);

    $docLocation = DocumentLocation::create(['birth_certificate' => 'students/1/documents/bc.pdf']);
    $student = Student::factory()->create([
        'school_id' => $school->id,
        'document_location_id' => $docLocation->id,
    ]);

    StudentDocumentRecord::query()->create([
        'school_id' => $school->id,
        'student_id' => $student->id,
        'document_key' => 'birth_certificate',
        'status' => StudentDocumentRecord::StatusVerified,
    ]);

    StudentHold::query()->create([
        'school_id' => $school->id,
        'student_id' => $student->id,
        'office' => 'registrar',
        'hold_type' => 'document',
        'severity' => StudentHold::SeverityBlocking,
        'title' => 'Missing Form 137',
        'reason' => 'Required before finals',
        'status' => StudentHold::StatusActive,
    ]);

    $student->delete();

    actingAs($admin)
        ->delete(route('administrators.students.bulk-force-destroy'), [
            'student_ids' => [$student->id],
            'confirm_text' => 'PERMANENTLY DELETE 1 STUDENT',
        ])
        ->assertRedirect();

    expect(Student::withTrashed()->find($student->id))->toBeNull()
        ->and(DocumentLocation::find($docLocation->id))->toBeNull()
        ->and(StudentDocumentRecord::where('student_id', $student->id)->count())->toBe(0)
        ->and(StudentHold::where('student_id', $student->id)->count())->toBe(0);
});

it('supports server-side compliance filtering, sorting, per_page, and matrix columns on the documents hub', function (): void {
    $school = School::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);

    $studentA = Student::factory()->create([
        'school_id' => $school->id,
        'first_name' => 'Alpha',
        'last_name' => 'Aardvark',
    ]);
    $studentB = Student::factory()->create([
        'school_id' => $school->id,
        'first_name' => 'Zeta',
        'last_name' => 'Zephyr',
    ]);

    StudentDocumentRecord::query()->create([
        'school_id' => $school->id,
        'student_id' => $studentA->id,
        'document_key' => 'birth_certificate',
        'status' => StudentDocumentRecord::StatusPendingReview,
    ]);

    actingAs($admin)
        ->get(route('administrators.students.documents.list', [
            'compliance' => 'pending_review',
            'sort' => 'name',
            'direction' => 'desc',
            'per_page' => 25,
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/students/documents/list', false)
            ->has('students.data', 1)
            ->where('students.data.0.id', $studentA->id)
            ->where('students.per_page', 25)
            ->where('filters.compliance', 'pending_review')
            ->where('filters.sort', 'name')
            ->where('filters.direction', 'desc')
            ->has('matrix_columns')
            ->has('column_stats')
            ->has('kpis')
        );
});

it('reviews fixed documents with physical copy compliance, rejection notifications, and two-way enrollment requirement sync', function (): void {
    Notification::fake();

    $school = School::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);
    $studentUser = User::factory()->create(['role' => UserRole::Student, 'school_id' => $school->id]);
    $student = Student::factory()->create([
        'school_id' => $school->id,
        'user_id' => $studentUser->id,
        'email' => $studentUser->email,
    ]);

    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'school_id' => $school->id,
    ]);

    $requirement = EnrollmentRequirement::query()->create([
        'student_enrollment_id' => $enrollment->id,
        'requirement_key' => 'birth_certificate',
        'label' => 'PSA Birth Certificate',
        'is_required' => true,
        'status' => EnrollmentRequirement::Pending,
    ]);

    // 1) Mark physical copy on file via Documents Hub -> compliant + syncs EnrollmentRequirement to Verified
    actingAs($admin)
        ->post(route('administrators.students.documents.fixed.review', [
            'student' => $student->id,
            'documentType' => 'birth_certificate',
        ]), [
            'action' => 'physical',
            'physical_copy_location' => 'Cabinet B · Drawer 2',
        ])
        ->assertRedirect();

    $record = StudentDocumentRecord::query()
        ->where('student_id', $student->id)
        ->where('document_key', 'birth_certificate')
        ->first();

    expect($record)->not->toBeNull()
        ->and($record->status)->toBe(StudentDocumentRecord::StatusPhysicalOnFile)
        ->and($record->isCompliant())->toBeTrue()
        ->and($requirement->fresh()->status)->toBe(EnrollmentRequirement::Verified);

    // 2) Reject document -> sets rejected, sends StudentDocumentStatusNotification, resets EnrollmentRequirement to Pending
    actingAs($admin)
        ->post(route('administrators.students.documents.fixed.review', [
            'student' => $student->id,
            'documentType' => 'birth_certificate',
        ]), [
            'action' => 'reject',
            'reason' => 'Scan is blurry around registration number.',
        ])
        ->assertRedirect();

    expect($record->fresh()->status)->toBe(StudentDocumentRecord::StatusRejected)
        ->and($record->fresh()->rejection_reason)->toBe('Scan is blurry around registration number.')
        ->and($requirement->fresh()->status)->toBe(EnrollmentRequirement::Pending);

    Notification::assertSentTo($studentUser, StudentDocumentStatusNotification::class);

    // 3) Reverse sync: verifying EnrollmentRequirement via EnrollmentRequirementService updates StudentDocumentRecord
    app(EnrollmentRequirementService::class)->verify($requirement->fresh(), $admin);

    expect($record->fresh()->status)->toBe(StudentDocumentRecord::StatusVerified);
});

it('streams fixed documents inline when preview=1 is requested and syncs status to the student portal', function (): void {
    $disk = (string) config('filesystems.private_disk', 'local');
    Storage::fake($disk);

    $school = School::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);
    $studentUser = User::factory()->create(['role' => UserRole::Student, 'school_id' => $school->id]);
    $student = Student::factory()->create([
        'school_id' => $school->id,
        'user_id' => $studentUser->id,
        'email' => $studentUser->email,
    ]);

    $file = UploadedFile::fake()->create('psa_birth.pdf', 120, 'application/pdf');
    app(StudentDocumentService::class)->uploadFixed($student, 'birth_certificate', $file, $admin, autoVerify: true);

    $response = actingAs($admin)
        ->get(route('administrators.students.documents.fixed.show', [
            'student' => $student->id,
            'documentType' => 'birth_certificate',
            'preview' => 1,
        ]))
        ->assertOk();

    expect((string) $response->headers->get('Content-Disposition'))->not->toContain('attachment');

    // Student portal reflects verified status
    actingAs($studentUser)
        ->get(route('student.documents.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('student/documents/index', false)
            ->where('summary.compliant', fn ($value) => $value >= 1)
            ->has('documents')
        );
});

it('sends bulk missing-document reminders to eligible students', function (): void {
    Notification::fake();

    $school = School::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);
    $studentUser = User::factory()->create(['role' => UserRole::Student, 'school_id' => $school->id]);
    $student = Student::factory()->create([
        'school_id' => $school->id,
        'user_id' => $studentUser->id,
        'email' => $studentUser->email,
    ]);

    actingAs($admin)
        ->post(route('administrators.students.documents.bulk-remind'), [
            'student_ids' => [$student->id],
        ])
        ->assertRedirect();

    Notification::assertSentTo($studentUser, StudentDocumentStatusNotification::class);
});

it('supports bulk restore and bulk promote actions', function (): void {
    $school = School::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);

    $trashed = Student::factory()->create(['school_id' => $school->id, 'academic_year' => 1]);
    $trashed->delete();

    actingAs($admin)
        ->post(route('administrators.students.bulk-restore'), [
            'student_ids' => [$trashed->id],
        ])
        ->assertRedirect();

    expect($trashed->fresh()->deleted_at)->toBeNull();

    actingAs($admin)
        ->patch(route('administrators.students.bulk-promote'), [
            'student_ids' => [$trashed->id],
        ])
        ->assertRedirect();

    expect((int) $trashed->fresh()->academic_year)->toBe(2);
});

it('validates and commits batch spreadsheet student intake', function (): void {
    $school = School::factory()->create();
    app(TenantContext::class)->setCurrentSchool($school);
    $admin = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);
    $course = Course::factory()->create(['school_id' => $school->id, 'code' => 'BSIT']);

    $existing = Student::factory()->create([
        'school_id' => $school->id,
        'email' => 'dup@example.com',
    ]);

    $rows = [
        [
            'first_name' => 'Maria',
            'last_name' => 'Clara',
            'birth_date' => '2004-06-15',
            'email' => 'maria.clara@example.com',
            'student_type' => StudentType::College->value,
            'course_code' => 'BSIT',
            'academic_year' => '1',
            'gender' => 'female',
        ],
        [
            'first_name' => 'Duplicate',
            'last_name' => 'Student',
            'birth_date' => '2004-07-10',
            'email' => $existing->email,
            'student_type' => StudentType::College->value,
            'course_code' => 'BSIT',
            'academic_year' => '1',
            'gender' => 'male',
        ],
        [
            'first_name' => '',
            'last_name' => 'InvalidRow',
            'birth_date' => '2004-07-10',
            'student_type' => StudentType::College->value,
            'course_code' => 'BSIT',
            'academic_year' => '1',
            'gender' => 'male',
        ],
    ];

    $validateResponse = actingAs($admin)
        ->postJson(route('administrators.students.batch-import.validate'), ['rows' => $rows])
        ->assertOk()
        ->assertJsonPath('summary.total', 3)
        ->assertJsonPath('summary.valid', 2)
        ->assertJsonPath('summary.errors', 1)
        ->assertJsonPath('summary.duplicates', 1);

    $normalizedFirstRow = $validateResponse->json('rows.0.normalized');

    actingAs($admin)
        ->postJson(route('administrators.students.batch-import.commit'), [
            'rows' => [$normalizedFirstRow],
        ])
        ->assertCreated()
        ->assertJsonPath('created_count', 1);

    $created = Student::query()->where('email', 'maria.clara@example.com')->first();
    expect($created)->not->toBeNull()
        ->and($created->course_id)->toBe($course->id);
});

it('supports quickStore for manual-ID and SHS schools and triage filters on index', function (): void {
    $school = School::factory()->create();
    app(TenantContext::class)->setCurrentSchool($school);
    GeneralSetting::factory()->create(['school_id' => $school->id]);

    app(IdentifierGenerator::class)->sequenceFor('student', schoolId: $school->id)->update([
        'assignment_mode' => 'manual',
    ]);

    $configService = app(StudentConfigurationService::class);
    $config = $configService->get($school->id);
    $config['enabled_types'] = [StudentType::SeniorHighSchool->value];
    $config['default_type'] = StudentType::SeniorHighSchool->value;
    $configService->update($config, $school->id);

    $strand = ShsStrand::query()->create([
        'strand_name' => 'STEM - Science, Technology, Engineering and Mathematics',
    ]);

    $admin = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);

    actingAs($admin)
        ->post(route('administrators.students.quick-create'), [
            'identifier_assignment' => 'manual',
            'student_id' => '20269988',
            'first_name' => 'Liza',
            'last_name' => 'Soberano',
            'gender' => 'female',
            'birth_date' => '2008-01-12',
            'email' => 'liza.shs@example.com',
            'student_type' => StudentType::SeniorHighSchool->value,
            'shs_strand_id' => $strand->id,
            'academic_year' => 11,
            'lrn' => '123456789012',
            'status' => 'enrolled',
        ])
        ->assertRedirect();

    $shsStudent = Student::query()->where('email', 'liza.shs@example.com')->first();
    expect($shsStudent)->not->toBeNull()
        ->and((string) $shsStudent->student_id)->toBe('20269988')
        ->and((int) $shsStudent->academic_year)->toBe(11)
        ->and($shsStudent->shs_strand_id)->toBe($strand->id);

    // Triage filter: no_portal finds this newly created student
    actingAs($admin)
        ->get(route('administrators.students.index', ['triage' => 'no_portal', 'year_level' => '11']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/students/index', false)
            ->has('students.data', 1)
            ->where('students.data.0.id', $shsStudent->id)
        );
});
