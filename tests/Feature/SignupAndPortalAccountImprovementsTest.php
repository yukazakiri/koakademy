<?php

declare(strict_types=1);

use App\Enums\StudentStatus;
use App\Enums\StudentType;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('blocks rejected, withdrawn, dropped, and applicant students from self-signup lookup', function (string $status) {
    $course = Course::factory()->create();

    Student::factory()->create([
        'email' => "blocked-{$status}@test.com",
        'student_type' => StudentType::College,
        'course_id' => $course->id,
        'status' => $status,
    ]);

    $this->postJson(route('signup.email-lookup'), [
        'email' => "blocked-{$status}@test.com",
    ])->assertOk()->assertJson([
        'found' => false,
    ]);
})->with([
    StudentStatus::Applicant->value,
    StudentStatus::Rejected->value,
    StudentStatus::Withdrawn->value,
    StudentStatus::Dropped->value,
    StudentStatus::Transferred->value,
]);

it('returns auto-detected signup hints only when include_hints is requested', function () {
    $course = Course::factory()->create();

    $student = Student::factory()->create([
        'email' => 'hints-student@test.com',
        'student_type' => StudentType::College,
        'course_id' => $course->id,
        'status' => StudentStatus::Enrolled->value,
    ]);

    $this->postJson(route('signup.email-lookup'), [
        'email' => 'hints-student@test.com',
        'include_hints' => true,
    ])->assertOk()->assertJson([
        'found' => true,
        'type' => 'student',
        'hints' => [
            'student_type' => 'college',
            'identifier_type' => 'student_id',
        ],
    ])->assertJsonMissingPath('hints.record_id');
});

it('refuses to overwrite an admin user when provisioning a student portal account', function () {
    $admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'email' => 'protected-admin@test.com']);
    $course = Course::factory()->create();
    $student = Student::factory()->create([
        'email' => 'protected-admin@test.com',
        'course_id' => $course->id,
    ]);

    $this->actingAs($admin)
        ->postJson(route('administrators.students.portal-account', $student), [
            'mode' => 'auto',
            'send_reset_link' => false,
        ])
        ->assertStatus(422);

    expect($admin->fresh()->role)->toBe(UserRole::SuperAdmin);
});

it('falls back to eligible student record when an inactive faculty shares the same email', function () {
    $course = Course::factory()->create();

    Faculty::factory()->create([
        'email' => 'dual-role@test.com',
        'status' => 'inactive',
    ]);

    Student::factory()->create([
        'email' => 'dual-role@test.com',
        'student_type' => StudentType::College,
        'course_id' => $course->id,
        'status' => StudentStatus::Enrolled->value,
    ]);

    $this->postJson(route('signup.email-lookup'), [
        'email' => 'dual-role@test.com',
    ])->assertOk()->assertExactJson([
        'found' => true,
        'type' => 'student',
    ]);
});

it('routes non-admin portal staff roles to administrators portal from homeUrl', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);

    expect($user->homeUrl())->toBe('/administrators');
})->with([
    UserRole::Registrar,
    UserRole::Cashier,
    UserRole::AccountingOfficer,
    UserRole::HRManager,
    UserRole::Librarian,
]);

it('syncs Spatie role and organization when provisioning or repairing a faculty portal account', function () {
    $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $school = School::factory()->create();
    app(TenantContext::class)->setCurrentSchool($school);

    $faculty = Faculty::factory()->create([
        'school_id' => $school->id,
        'email' => 'Faculty.Sync@Test.com',
        'faculty_id_number' => 'FAC-SYNC-01',
    ]);

    $this->actingAs($admin)
        ->post(route('administrators.faculties.portal-account', $faculty), [
            'mode' => 'create',
            'role' => UserRole::Instructor->value,
            'send_reset_link' => false,
        ])
        ->assertRedirect();

    $portalUser = User::query()->whereRaw('LOWER(email) = ?', ['faculty.sync@test.com'])->firstOrFail();
    expect($portalUser->hasRole(UserRole::Instructor->value))->toBeTrue();

    $this->actingAs($admin)
        ->post(route('administrators.faculties.portal-account', $faculty), [
            'mode' => 'repair',
            'role' => UserRole::Professor->value,
            'send_reset_link' => false,
        ])
        ->assertRedirect();

    expect($portalUser->fresh()->hasRole(UserRole::Professor->value))->toBeTrue()
        ->and($portalUser->fresh()->role)->toBe(UserRole::Professor);
});

it('provisions a modern User portal account for a student and supports quick-create duplicate detection', function () {
    Storage::fake('private');
    $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $school = School::factory()->create();
    app(TenantContext::class)->setCurrentSchool($school);
    $course = Course::factory()->create();

    $payload = [
        'student_type' => StudentType::College->value,
        'first_name' => 'Maria',
        'last_name' => 'Peña',
        'gender' => 'female',
        'birth_date' => '2004-05-12',
        'email' => 'maria.pena@test.com',
        'course_id' => $course->id,
        'academic_year' => 1,
        'provision_portal_account' => true,
        'send_activation_link' => false,
    ];

    $createResponse = $this->actingAs($admin)
        ->postJson(route('administrators.students.quick-create'), $payload)
        ->assertCreated();

    $studentId = $createResponse->json('student.id');
    $student = Student::query()->findOrFail($studentId);

    expect($student->user_id)->not->toBeNull();
    expect($student->user?->hasRole(UserRole::Student->value))->toBeTrue();

    // Second identical quick-create returns 409 duplicate warning unless allow_duplicate is set
    $this->actingAs($admin)
        ->postJson(route('administrators.students.quick-create'), $payload)
        ->assertStatus(409)
        ->assertJsonCount(1, 'duplicates');

    // Photo & signature uploads persist cleanly on student update
    $this->actingAs($admin)
        ->post(route('administrators.students.update', $student), [
            '_method' => 'put',
            'student_type' => StudentType::College->value,
            'student_id' => $student->student_id,
            'first_name' => 'Maria',
            'last_name' => 'Peña',
            'gender' => 'female',
            'birth_date' => '2004-05-12',
            'email' => 'maria.pena@test.com',
            'course_id' => $course->id,
            'academic_year' => 1,
            'status' => StudentStatus::Enrolled->value,
            'picture_1x1' => UploadedFile::fake()->image('id.png', 200, 200),
            'signature' => UploadedFile::fake()->image('sig.png', 200, 80),
        ])
        ->assertRedirect();

    $student->refresh();
    expect($student->DocumentLocation?->picture_1x1)->not->toBeNull()
        ->and($student->signature_path)->not->toBeNull();
});
