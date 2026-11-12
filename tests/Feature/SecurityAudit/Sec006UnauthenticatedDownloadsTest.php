<?php

declare(strict_types=1);

namespace Tests\Feature\SecurityAudit;

use App\Enums\EnrollStat;
use App\Enums\UserRole;
use App\Jobs\SendClassChangeNotificationJob;
use App\Mail\ClassChangeNotification;
use App\Models\Course;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\GeneralSetting;
use App\Models\Resource;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * SEC-006 · Assessment and export downloads are reachable without authentication (DCC-11).
 *
 * Verifies that:
 * 1. GET /assessment/download/{record} requires auth and admin portal access with View:StudentEnrollment permission.
 * 2. Anonymous requests are rejected (redirect/401) before reaching the handler (no 404 enumeration).
 * 3. Soft-deleted student enrollments cannot be enumerated (returns 404 for authorized admins).
 * 4. GET /download/student-list/{filename} requires auth and is forbidden to students.
 * 5. GET /download/timetable-pdf/{filename} requires auth and is forbidden to students.
 * 6. Path traversal attempts on download routes are rejected with 400 Bad Request.
 * 7. Authorized faculty and administrators can download files from private storage with nosniff headers.
 * 8. Exports are written to the private export disk, and legacy copies are relocated off the public disk.
 * 9. The class change notification reads its attachment from the disk the PDF actually lives on.
 */
beforeEach(function (): void {
    Storage::fake('private');
    Storage::fake('public');

    GeneralSetting::factory()->create([
        'school_starting_date' => '2026-06-01',
        'school_ending_date' => '2027-03-31',
        'semester' => 1,
    ]);

    Permission::findOrCreate('View:StudentEnrollment', 'web');
    Permission::findOrCreate('ViewAny:StudentEnrollment', 'web');

    Role::findOrCreate('super_admin', 'web');
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('instructor', 'web');
    Role::findOrCreate('student', 'web');

    $this->school = School::factory()->create();

    $this->department = Department::factory()->create([
        'school_id' => $this->school->id,
    ]);

    $this->course = Course::factory()->create([
        'school_id' => $this->school->id,
        'department_id' => $this->department->id,
    ]);

    $this->student = Student::factory()->create([
        'school_id' => $this->school->id,
        'course_id' => $this->course->id,
    ]);

    $this->studentUser = User::factory()->create([
        'role' => UserRole::Student,
        'email' => $this->student->email,
        'record_id' => $this->student->id,
    ]);

    $this->faculty = Faculty::factory()->create([
        'school_id' => $this->school->id,
    ]);

    $this->facultyUser = User::factory()->create([
        'role' => UserRole::Instructor,
        'email' => $this->faculty->email,
        'record_id' => $this->faculty->id,
        'faculty_id_number' => $this->faculty->faculty_id_number,
    ]);

    $this->adminUser = User::factory()->create([
        'role' => UserRole::Admin,
    ]);
    $this->adminUser->givePermissionTo('View:StudentEnrollment');

    $this->unprivilegedAdmin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $this->superAdminUser = User::factory()->create([
        'role' => UserRole::SuperAdmin,
    ]);

    $this->enrollment = StudentEnrollment::factory()->create([
        'student_id' => $this->student->id,
        'course_id' => $this->course->id,
        'school_id' => $this->school->id,
    ]);
});

it('blocks anonymous callers from assessment download route without running record lookup', function (): void {
    // Web request redirects to login
    $this->get(portalUrlForAdministrators("/assessment/download/{$this->enrollment->id}"))
        ->assertRedirect('/login');

    // JSON request gets 401 Unauthorized
    $this->getJson(portalUrlForAdministrators("/assessment/download/{$this->enrollment->id}"))
        ->assertUnauthorized();

    // Anonymous request for non-existent record also stopped by auth (not reaching 404 handler)
    $this->get(portalUrlForAdministrators('/assessment/download/999999'))
        ->assertRedirect('/login');

    $this->getJson(portalUrlForAdministrators('/assessment/download/999999'))
        ->assertUnauthorized();
});

it('blocks students from assessment download route', function (): void {
    $this->actingAs($this->studentUser)
        ->get(portalUrlForAdministrators("/assessment/download/{$this->enrollment->id}"))
        ->assertForbidden();
});

it('blocks administrators lacking View:StudentEnrollment from assessment download route', function (): void {
    $this->actingAs($this->unprivilegedAdmin)
        ->get(portalUrlForAdministrators("/assessment/download/{$this->enrollment->id}"))
        ->assertForbidden();
});

it('returns 404 for soft-deleted student enrollments on assessment download route', function (): void {
    $this->enrollment->delete();
    expect($this->enrollment->trashed())->toBeTrue();

    $this->actingAs($this->adminUser)
        ->get(portalUrlForAdministrators("/assessment/download/{$this->enrollment->id}"))
        ->assertNotFound();
});

it('allows authorized administrators to download assessment PDF from private storage', function (): void {
    $pdfContent = '%PDF-1.4 Fake Assessment PDF Content';
    $filePath = 'assessments/assessment-test-123.pdf';
    Storage::disk('private')->put($filePath, $pdfContent);

    Resource::create([
        'resourceable_id' => $this->enrollment->id,
        'resourceable_type' => StudentEnrollment::class,
        'type' => 'assessment',
        'file_path' => $filePath,
        'file_name' => 'assessment-test-123.pdf',
        'mime_type' => 'application/pdf',
        'disk' => 'private',
        'file_size' => mb_strlen($pdfContent),
    ]);

    $response = $this->actingAs($this->adminUser)
        ->get(portalUrlForAdministrators("/assessment/download/{$this->enrollment->id}"));

    $response->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="assessment-test-123.pdf"')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->getContent())->toBe($pdfContent);
});

it('relocates legacy assessment files off the web-exposed public disk when an authorized download reads them', function (): void {
    $pdfContent = '%PDF-1.4 Legacy Assessment PDF';
    $filePath = 'assessments/legacy-assessment.pdf';
    Storage::disk('public')->put($filePath, $pdfContent);

    Resource::create([
        'resourceable_id' => $this->enrollment->id,
        'resourceable_type' => StudentEnrollment::class,
        'type' => 'assessment',
        'file_path' => $filePath,
        'file_name' => 'legacy-assessment.pdf',
        'mime_type' => 'application/pdf',
        'disk' => 'public',
        'file_size' => mb_strlen($pdfContent),
    ]);

    $response = $this->actingAs($this->superAdminUser)
        ->get(portalUrlForAdministrators("/assessment/download/{$this->enrollment->id}"));

    $response->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="legacy-assessment.pdf"')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->getContent())->toBe($pdfContent);

    // The copy on the web-exposed disk is gone; the private disk holds it now.
    Storage::disk('public')->assertMissing($filePath);
    Storage::disk('private')->assertExists($filePath);
    expect(Storage::disk('private')->get($filePath))->toBe($pdfContent);
});

it('honours a configured private export disk', function (): void {
    config(['filesystems.private_disk' => 'private']);

    expect(\App\Support\PrivateExportStorage::disk())->toBe('private');

    config(['filesystems.private_disk' => 'shared-exports']);
    Storage::fake('shared-exports');

    expect(\App\Support\PrivateExportStorage::disk())->toBe('shared-exports');

    $pdfContent = '%PDF-1.4 Shared Export';
    $filename = 'shared-student-list.pdf';
    $path = 'exports/student-lists/'.$filename;
    Storage::disk('shared-exports')->put($path, $pdfContent);

    $response = $this->actingAs($this->facultyUser)
        ->get("/download/student-list/{$filename}");

    $response->assertOk();
    expect($response->getContent())->toBe($pdfContent);
});

it('blocks anonymous callers from downloading student lists', function (): void {
    $this->get('/download/student-list/test_roster.pdf')
        ->assertRedirect('/login');

    $this->getJson('/download/student-list/test_roster.pdf')
        ->assertUnauthorized();
});

it('blocks students from downloading student list exports', function (): void {
    $this->actingAs($this->studentUser)
        ->get('/download/student-list/test_roster.pdf')
        ->assertForbidden();
});

it('blocks path traversal attempts on student list download route', function (string $payload): void {
    $this->actingAs($this->adminUser)
        ->get('/download/student-list/'.urlencode($payload))
        ->assertStatus(400);
})->with([
    'dot dot slash' => ['../../secret.pdf'],
    'nested dot dot' => ['subdir/../../secret.pdf'],
    'forward slash' => ['subdir/secret.pdf'],
    'backslash' => ['subdir\\secret.pdf'],
]);

it('allows faculty and administrators to download student list from private storage', function (): void {
    $pdfContent = '%PDF-1.4 Student Roster Content';
    $filename = 'student_list_CS101_SEC1.pdf';
    $path = 'exports/student-lists/'.$filename;
    Storage::disk('private')->put($path, $pdfContent);

    // Faculty download
    $facultyResponse = $this->actingAs($this->facultyUser)
        ->get("/download/student-list/{$filename}");

    $facultyResponse->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="'.$filename.'"')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($facultyResponse->getContent())->toBe($pdfContent);

    // Admin download
    $adminResponse = $this->actingAs($this->adminUser)
        ->get("/download/student-list/{$filename}");

    $adminResponse->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="'.$filename.'"')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($adminResponse->getContent())->toBe($pdfContent);
});

it('blocks anonymous callers from downloading timetable PDFs', function (): void {
    $this->get('/download/timetable-pdf/schedule-timetable-12345.pdf')
        ->assertRedirect('/login');

    $this->getJson('/download/timetable-pdf/schedule-timetable-12345.pdf')
        ->assertUnauthorized();
});

it('blocks students from downloading timetable PDFs', function (): void {
    $this->actingAs($this->studentUser)
        ->get('/download/timetable-pdf/schedule-timetable-12345.pdf')
        ->assertForbidden();
});

it('blocks path traversal attempts on timetable PDF download route', function (string $payload): void {
    $this->actingAs($this->facultyUser)
        ->get('/download/timetable-pdf/'.urlencode($payload))
        ->assertStatus(400);
})->with([
    'dot dot slash' => ['../../secret.pdf'],
    'nested dot dot' => ['subdir/../../secret.pdf'],
    'forward slash' => ['subdir/secret.pdf'],
    'backslash' => ['subdir\\secret.pdf'],
]);

it('allows faculty and administrators to download timetable PDF from private storage', function (): void {
    $pdfContent = '%PDF-1.4 Timetable PDF Content';
    $filename = 'schedule-timetable-12345.pdf';
    $path = 'schedules/'.$filename;
    Storage::disk('private')->put($path, $pdfContent);

    // Faculty download
    $facultyResponse = $this->actingAs($this->facultyUser)
        ->get("/download/timetable-pdf/{$filename}");

    $facultyResponse->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="'.$filename.'"')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($facultyResponse->getContent())->toBe($pdfContent);

    // Admin download
    $adminResponse = $this->actingAs($this->adminUser)
        ->get("/download/timetable-pdf/{$filename}");

    $adminResponse->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="'.$filename.'"')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($adminResponse->getContent())->toBe($pdfContent);
});

it('verifies assessment, student list, and timetable PDF jobs write to the private export disk', function (): void {
    $jobs = [
        'Jobs/GenerateAssessmentPdfJob.php' => '$storageDisk = PrivateExportStorage::disk();',
        'Jobs/GenerateStudentListPdfJob.php' => '$disk = PrivateExportStorage::disk();',
        'Jobs/GenerateTimetablePdfJob.php' => '$disk = PrivateExportStorage::disk();',
        'Jobs/GenerateStudentTimetablePdfJob.php' => '$disk = PrivateExportStorage::disk();',
        'Jobs/SendAssessmentNotificationJob.php' => '$storageDisk = PrivateExportStorage::disk();',
        'Jobs/SendClassChangeNotificationJob.php' => '$storageDisk = PrivateExportStorage::disk();',
        'Notifications/MigrateToStudent.php' => '$storageDisk = PrivateExportStorage::disk();',
    ];

    foreach ($jobs as $relativePath => $expected) {
        $code = (string) file_get_contents(app_path($relativePath));

        expect($code)->toContain($expected)
            ->and($code)->toContain("['visibility' => 'private']")
            ->and($code)->not->toContain("['visibility' => 'public']");
    }
});

it('verifies the export directories that must never stay on a web-exposed disk', function (): void {
    expect(\App\Support\PrivateExportStorage::EXPORT_DIRECTORIES)
        ->toContain('assessments')
        ->toContain('exports/student-lists')
        ->toContain('schedules');
});

it('reads the class change notification attachment from the disk the PDF lives on', function (): void {
    config(['filesystems.default' => 'class-change-default']);
    config(['filesystems.private_disk' => 'private']);
    Storage::fake('class-change-default');
    Storage::fake('private');

    $course = Course::factory()->create();
    $student = Student::factory()->create([
        'course_id' => $course->id,
        'email' => 'class-change@example.test',
    ]);

    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'status' => EnrollStat::VerifiedByCashier->value,
    ]);

    $pdfContent = '%PDF-1.4 Class Change Assessment';
    $filePath = 'assessments/assmt-class-change.pdf';
    Storage::disk('private')->put($filePath, $pdfContent);

    Resource::create([
        'resourceable_id' => $enrollment->id,
        'resourceable_type' => StudentEnrollment::class,
        'type' => 'assessment',
        'file_path' => $filePath,
        'file_name' => 'assmt-class-change.pdf',
        'mime_type' => 'application/pdf',
        'disk' => 'private',
        'file_size' => mb_strlen($pdfContent),
    ]);

    Mail::fake();

    $job = new SendClassChangeNotificationJob($enrollment, [], 'Section change');
    $job->handle();

    Mail::assertSent(ClassChangeNotification::class, function (ClassChangeNotification $mail) use ($pdfContent): bool {
        return $mail->attachmentData !== null
            && $mail->attachmentData['contents'] === $pdfContent
            && $mail->attachmentData['name'] === 'assmt-class-change.pdf';
    });
});

it('moves legacy exports off the public disk with the migration command', function (): void {
    config(['filesystems.private_disk' => 'private']);
    Storage::fake('private');
    Storage::fake('public');

    Storage::disk('public')->put('assessments/legacy-assessment.pdf', 'legacy-assessment');
    Storage::disk('public')->put('exports/student-lists/legacy-roster.pdf', 'legacy-roster');
    Storage::disk('public')->put('schedules/legacy-schedule.pdf', 'legacy-schedule');
    Storage::disk('public')->put('avatars/keep-me.png', 'keep-me');

    $this->artisan('exports:migrate-to-private')->assertSuccessful();

    Storage::disk('public')->assertMissing('assessments/legacy-assessment.pdf');
    Storage::disk('public')->assertMissing('exports/student-lists/legacy-roster.pdf');
    Storage::disk('public')->assertMissing('schedules/legacy-schedule.pdf');

    Storage::disk('private')->assertExists('assessments/legacy-assessment.pdf');
    Storage::disk('private')->assertExists('exports/student-lists/legacy-roster.pdf');
    Storage::disk('private')->assertExists('schedules/legacy-schedule.pdf');

    // Unrelated public assets stay where they are.
    Storage::disk('public')->assertExists('avatars/keep-me.png');
});
