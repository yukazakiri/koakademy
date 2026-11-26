<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Models\ClassAttendanceRecord;
use App\Models\ClassAttendanceSession;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\FeatureToggleRegistry;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Laravel\Pennant\Feature;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    GeneralSetting::factory()->create([
        'school_starting_date' => '2025-08-01',
        'school_ending_date' => '2026-05-30',
        'semester' => 1,
    ]);
});

function duplicateScannerPath(string $routeName): string
{
    $url = route($routeName, [], false);

    return portalUrlForAdministrators($url);
}

function createFacultyWithClass(School $school, string $email, string $subjectCode = 'CS101', string $section = 'A'): array
{
    $user = User::factory()->create([
        'role' => UserRole::Instructor,
        'email' => $email,
        'school_id' => $school->id,
        'faculty_id_number' => 'FAC-'.Str::upper(Str::random(5)),
    ]);

    $faculty = Faculty::query()->create([
        'id' => (string) Str::uuid(),
        'first_name' => 'Prof',
        'last_name' => 'Tester',
        'email' => $email,
        'password' => bcrypt('password'),
        'status' => 'active',
        'faculty_id_number' => $user->faculty_id_number,
        'school_id' => $school->id,
    ]);

    $class = Classes::query()->create([
        'subject_code' => $subjectCode,
        'section' => $section,
        'faculty_id' => $faculty->id,
        'school_year' => '2025-2026',
        'semester' => 1,
        'maximum_slots' => 40,
        'classification' => 'college',
        'school_id' => $school->id,
    ]);

    return [$user, $faculty, $class];
}

it('detects duplicate student clusters by email and by name+dob and previews/merges cleanly with authorization', function (): void {
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();

    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => $schoolA->id,
    ]);

    app(TenantContext::class)->setCurrentSchool($schoolA);

    // Email duplicate cluster (case-insensitive)
    $emailTarget = Student::factory()->create([
        'school_id' => $schoolA->id,
        'institution_id' => $schoolA->id,
        'first_name' => 'Elena',
        'last_name' => 'Cruz',
        'email' => 'Elena.Cruz@Example.edu',
        'birth_date' => '2003-04-10',
    ]);
    $emailSource = Student::factory()->create([
        'school_id' => $schoolA->id,
        'institution_id' => $schoolA->id,
        'first_name' => 'E.',
        'last_name' => 'Cruz',
        'email' => 'elena.cruz@example.edu',
        'birth_date' => '2003-04-12',
    ]);

    // Name + DOB duplicate cluster (different emails)
    $identityOne = Student::factory()->create([
        'school_id' => $schoolA->id,
        'institution_id' => $schoolA->id,
        'first_name' => 'Marco',
        'last_name' => 'Reyes',
        'email' => 'marco.one@example.edu',
        'birth_date' => '2004-09-15',
    ]);
    $identityTwo = Student::factory()->create([
        'school_id' => $schoolA->id,
        'institution_id' => $schoolA->id,
        'first_name' => 'marco',
        'last_name' => 'REYES',
        'email' => 'marco.two@example.edu',
        'birth_date' => '2004-09-15',
    ]);

    // Trashed duplicate should be ignored
    $trashedStudent = Student::factory()->create([
        'school_id' => $schoolA->id,
        'institution_id' => $schoolA->id,
        'first_name' => 'Unique',
        'last_name' => 'Student',
        'email' => 'unique@example.edu',
        'birth_date' => '2002-01-01',
    ]);
    $trashedStudent->delete();

    // Another school's student with same email should not bleed into school A
    $otherSchoolStudent = Student::factory()->create([
        'school_id' => $schoolB->id,
        'institution_id' => $schoolB->id,
        'email' => 'unique@example.edu',
        'birth_date' => '2002-01-01',
    ]);

    // Attach a class enrollment to emailSource so preview/merge moves real data
    [, , $class] = createFacultyWithClass($schoolA, 'dup-faculty@example.edu');
    $sourceClassEnrollment = ClassEnrollment::query()->create([
        'class_id' => $class->id,
        'student_id' => $emailSource->id,
        'status' => true,
        'school_id' => $schoolA->id,
    ]);

    $this->actingAs($admin)
        ->get(duplicateScannerPath('administrators.students.duplicates.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/students/duplicates', false)
            ->where('stats.total_clusters', 2)
            ->where('stats.email_clusters', 1)
            ->where('stats.identity_clusters', 1)
            ->where('stats.affected_students', 4),
        );

    // Preview transfer impact
    $this->actingAs($admin)
        ->postJson(duplicateScannerPath('administrators.students.duplicates.preview'), [
            'source_student_id' => $emailSource->id,
            'target_student_id' => $emailTarget->id,
        ])
        ->assertOk()
        ->assertJsonPath('safe_to_proceed', true)
        ->assertJsonPath('records_to_transfer.class_enrollments_count', 1);

    // Merge and archive source
    $this->actingAs($admin)
        ->post(duplicateScannerPath('administrators.students.duplicates.merge'), [
            'source_student_id' => $emailSource->id,
            'target_student_id' => $emailTarget->id,
            'archive_source' => true,
        ])
        ->assertRedirect();

    $sourceClassEnrollment->refresh();
    expect($sourceClassEnrollment->student_id)->toBe($emailTarget->id);
    expect(Student::withoutSchoolScope()->withTrashed()->find($emailSource->id)?->trashed())->toBeTrue();

    // Cross-school preview is blocked
    $this->actingAs($admin)
        ->postJson(duplicateScannerPath('administrators.students.duplicates.preview'), [
            'source_student_id' => $otherSchoolStudent->id,
            'target_student_id' => $emailTarget->id,
        ])
        ->assertNotFound();

    // Non-admin user (student role) cannot access duplicate scanner or merge
    $studentUser = User::factory()->create([
        'role' => UserRole::Student,
        'school_id' => $schoolA->id,
    ]);
    $this->actingAs($studentUser)
        ->post(duplicateScannerPath('administrators.students.duplicates.merge'), [
            'source_student_id' => $identityOne->id,
            'target_student_id' => $identityTwo->id,
        ])
        ->assertForbidden();
});

it('computes live faculty at-risk alerts and excludes other faculty classes', function (): void {
    $school = School::factory()->create();
    app(TenantContext::class)->setCurrentSchool($school);

    [$facultyUserA, $facultyA, $classA] = createFacultyWithClass($school, 'faculty-risk-a@example.edu', 'MATH201', 'A');
    [, , $classB] = createFacultyWithClass($school, 'faculty-risk-b@example.edu', 'HIST101', 'B');

    $highRiskStudent = Student::factory()->create([
        'school_id' => $school->id,
        'first_name' => 'Alex',
        'last_name' => 'Rivera',
    ]);
    $attendanceRiskStudent = Student::factory()->create([
        'school_id' => $school->id,
        'first_name' => 'Bianca',
        'last_name' => 'Santos',
    ]);
    $healthyStudent = Student::factory()->create([
        'school_id' => $school->id,
        'first_name' => 'Carlos',
        'last_name' => 'Mendoza',
    ]);
    $otherFacultyStudent = Student::factory()->create([
        'school_id' => $school->id,
        'first_name' => 'Dana',
        'last_name' => 'Other',
    ]);

    $highRiskEnrollment = ClassEnrollment::query()->create([
        'class_id' => $classA->id,
        'student_id' => $highRiskStudent->id,
        'status' => true,
        'prelim_grade' => 68.00,
        'midterm_grade' => 70.00,
        'total_average' => 69.00,
        'school_id' => $school->id,
    ]);
    $attendanceEnrollment = ClassEnrollment::query()->create([
        'class_id' => $classA->id,
        'student_id' => $attendanceRiskStudent->id,
        'status' => true,
        'prelim_grade' => 88.00,
        'midterm_grade' => 86.00,
        'school_id' => $school->id,
    ]);
    ClassEnrollment::query()->create([
        'class_id' => $classA->id,
        'student_id' => $healthyStudent->id,
        'status' => true,
        'prelim_grade' => 92.00,
        'midterm_grade' => 90.00,
        'total_average' => 91.00,
        'school_id' => $school->id,
    ]);
    ClassEnrollment::query()->create([
        'class_id' => $classB->id,
        'student_id' => $otherFacultyStudent->id,
        'status' => true,
        'prelim_grade' => 55.00,
        'total_average' => 55.00,
        'school_id' => $school->id,
    ]);

    // Record 3 absences for attendanceRiskStudent and 3 absences for highRiskStudent
    foreach (['2025-09-01', '2025-09-03', '2025-09-05'] as $date) {
        $session = ClassAttendanceSession::query()->create([
            'class_id' => $classA->id,
            'session_date' => $date,
            'taken_by' => $facultyA->id,
        ]);

        ClassAttendanceRecord::query()->create([
            'class_attendance_session_id' => $session->id,
            'class_enrollment_id' => $attendanceEnrollment->id,
            'class_id' => $classA->id,
            'student_id' => $attendanceRiskStudent->id,
            'status' => AttendanceStatus::Absent,
        ]);

        ClassAttendanceRecord::query()->create([
            'class_attendance_session_id' => $session->id,
            'class_enrollment_id' => $highRiskEnrollment->id,
            'class_id' => $classA->id,
            'student_id' => $highRiskStudent->id,
            'status' => AttendanceStatus::Absent,
        ]);
    }

    // Add one session today to also verify /faculty/attendance enrichment
    ClassAttendanceSession::query()->create([
        'class_id' => $classA->id,
        'session_date' => now()->toDateString(),
        'taken_by' => $facultyA->id,
    ]);

    foreach (['faculty-at-risk-alerts', 'faculty-grades', 'faculty-attendance'] as $featureKey) {
        if ($featureClass = FeatureToggleRegistry::classForKey($featureKey)) {
            Feature::for($facultyUserA)->activate($featureClass);
        }
    }

    $this->actingAs($facultyUserA)
        ->get('/faculty/at-risk-alerts')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('faculty/at-risk-alerts/index', false)
            ->where('summary.total_alerts', 2)
            ->where('summary.high_risk', 1)
            ->where('summary.attendance_risk', 2)
            ->where('summary.academic_risk', 1)
            ->where('summary.classes_monitored', 1)
            ->has('alerts', 2)
            ->where('alerts.0.student_name', $highRiskStudent->full_name)
            ->where('alerts.0.risk_level', 'high')
            ->where('alerts.1.student_name', $attendanceRiskStudent->full_name)
            ->where('alerts.1.risk_level', 'medium'),
        );

    // Filter by risk_level=high
    $this->actingAs($facultyUserA)
        ->get('/faculty/at-risk-alerts?risk_level=high')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('alerts', 1)
            ->where('alerts.0.student_name', $highRiskStudent->full_name),
        );

    // Verify enriched /faculty/grades overview
    $this->actingAs($facultyUserA)
        ->get('/faculty/grades')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('faculty/grades/index', false)
            ->where('faculty_data.classes.0.total_students', 3)
            ->where('faculty_data.classes.0.graded_count', 3)
            ->where('faculty_data.classes.0.finalized_count', 0),
        );

    // Verify enriched /faculty/attendance overview
    $this->actingAs($facultyUserA)
        ->get('/faculty/attendance')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('faculty/attendance/index', false)
            ->where('faculty_data.classes.0.sessions_count', 4)
            ->where('faculty_data.classes.0.today_logged', true),
        );
});

it('exposes is_grades_finalized and gradesFinalized summary in FacultyClassController::show', function (): void {
    $school = School::factory()->create();
    app(TenantContext::class)->setCurrentSchool($school);

    [$facultyUser, , $class] = createFacultyWithClass($school, 'faculty-grades-lock@example.edu', 'CS202', 'B');

    $studentA = Student::factory()->create(['school_id' => $school->id]);
    $studentB = Student::factory()->create(['school_id' => $school->id]);

    ClassEnrollment::query()->create([
        'class_id' => $class->id,
        'student_id' => $studentA->id,
        'status' => true,
        'prelim_grade' => 88,
        'midterm_grade' => 90,
        'finals_grade' => 92,
        'total_average' => 90.2,
        'is_grades_finalized' => true,
        'school_id' => $school->id,
    ]);

    ClassEnrollment::query()->create([
        'class_id' => $class->id,
        'student_id' => $studentB->id,
        'status' => true,
        'prelim_grade' => 80,
        'is_grades_finalized' => false,
        'school_id' => $school->id,
    ]);

    $this->actingAs($facultyUser)
        ->get("/faculty/classes/{$class->id}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('faculty/classes/show', false)
            ->where('gradesFinalized.total', 2)
            ->where('gradesFinalized.finalized', 1)
            ->where('gradesFinalized.all_finalized', false)
            ->where('gradesFinalized.any_finalized', true)
            ->where('students.0.is_grades_finalized', true)
            ->where('students.1.is_grades_finalized', false),
        );
});

it('filters audit logs by subject_id and custom date range, scopes by tenant, and exports formula-safe CSV', function (): void {
    if (! Schema::hasTable('activity_log')) {
        $this->markTestSkipped('Activity log table not available in tests.');
    }

    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();

    $registrarA = User::factory()->create([
        'role' => UserRole::Registrar,
        'school_id' => $schoolA->id,
    ]);
    $registrarB = User::factory()->create([
        'role' => UserRole::Registrar,
        'school_id' => $schoolB->id,
    ]);

    app(TenantContext::class)->setCurrentSchool($schoolA);

    $studentA = Student::factory()->create(['school_id' => $schoolA->id]);
    $studentB = Student::factory()->create(['school_id' => $schoolB->id]);

    $matchingLog = Activity::query()->create([
        'log_name' => 'students',
        'description' => '=HYPERLINK("http://evil.test","Click") updated profile',
        'event' => 'updated',
        'subject_type' => Student::class,
        'subject_id' => $studentA->id,
        'causer_type' => User::class,
        'causer_id' => $registrarA->id,
        'properties' => ['ip' => '10.0.0.15'],
    ]);

    Activity::query()->whereKey($matchingLog->id)->update([
        'created_at' => '2025-10-15 10:00:00',
    ]);

    $oldLog = Activity::query()->create([
        'log_name' => 'students',
        'description' => 'Old profile change outside custom window',
        'event' => 'updated',
        'subject_type' => Student::class,
        'subject_id' => $studentA->id,
        'causer_type' => User::class,
        'causer_id' => $registrarA->id,
        'properties' => [],
    ]);

    Activity::query()->whereKey($oldLog->id)->update([
        'created_at' => '2025-01-05 10:00:00',
    ]);

    $otherTenantLog = Activity::query()->create([
        'log_name' => 'students',
        'description' => 'School B private audit entry',
        'event' => 'updated',
        'subject_type' => Student::class,
        'subject_id' => $studentB->id,
        'causer_type' => User::class,
        'causer_id' => $registrarB->id,
        'properties' => [],
    ]);

    Activity::query()->whereKey($otherTenantLog->id)->update([
        'created_at' => '2025-10-15 10:00:00',
    ]);

    // Filter by subject_id + custom date range + verify subject_url & tenant isolation
    $this->actingAs($registrarA)
        ->get(portalUrlForAdministrators("/administrators/audit-logs?subject_type=Student&subject_id={$studentA->id}&from=2025-10-01&to=2025-10-31"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/audit-logs/index', false)
            ->has('logs.data', 1)
            ->where('logs.data.0.id', $matchingLog->id)
            ->where('logs.data.0.subject_url', "/administrators/students/{$studentA->id}")
            ->where('filters.subject_id', (string) $studentA->id)
            ->where('filters.from', '2025-10-01')
            ->where('filters.to', '2025-10-31'),
        );

    // CSV export returns text/csv and neutralizes formula injection
    $response = $this->actingAs($registrarA)
        ->get(portalUrlForAdministrators("/administrators/audit-logs/export?subject_type=Student&subject_id={$studentA->id}&from=2025-10-01&to=2025-10-31"));

    $response->assertOk();
    expect((string) $response->headers->get('content-type'))->toContain('text/csv');

    $csv = $response->streamedContent();
    expect($csv)->toContain('timestamp,event,description,causer,subject_type,subject_id,ip_address');
    expect($csv)->toContain("'=HYPERLINK(");
    expect($csv)->toContain('10.0.0.15');
    expect($csv)->not->toContain('School B private audit entry');
    expect($csv)->not->toContain('Old profile change outside custom window');
});
