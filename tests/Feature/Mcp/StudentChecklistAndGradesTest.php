<?php

declare(strict_types=1);

use App\Ai\Tools\GetCurriculumProgressTool;
use App\Enums\UserRole;
use App\Mcp\Servers\KoAkademyServer;
use App\Mcp\Tools\GetAvailableSubjectsTool;
use App\Mcp\Tools\GetStudentChecklistTool;
use App\Models\Course;
use App\Models\Department;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\User;
use App\Services\TenantContext;
use Laravel\Ai\Tools\Request as AiRequest;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    config(['activitylog.enabled' => false]);
    config(['api.mcp.enabled' => true]);

    $this->school = School::factory()->create();

    $this->settings = GeneralSetting::create([
        'school_starting_date' => now()->startOfYear(),
        'school_ending_date' => now()->startOfYear()->addYear(),
        'semester' => 1,
        'site_name' => 'KoAkademy Checklist Test School',
    ]);

    $this->admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => $this->school->id,
    ]);

    app(TenantContext::class)->setCurrentSchool($this->school);

    $permissions = [
        'ViewAny:Student',
        'View:Student',
        'View:StudentEnrollment',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $this->admin->assignRole('super_admin');
});

it('prevents student checklist and eligibility tools from inferring hidden finals', function (): void {
    $this->settings->update(['more_configs' => ['grade_release' => ['require_clearance' => true]]]);
    $year = $this->settings->getSchoolYearString();
    $user = User::factory()->create(['role' => UserRole::Student, 'school_id' => $this->school->id]);
    $course = Course::factory()->create(['school_id' => $this->school->id]);
    $student = Student::factory()->create(['user_id' => $user->id, 'email' => $user->email, 'school_id' => $this->school->id, 'course_id' => $course->id, 'academic_year' => 1]);
    $subject = Subject::factory()->create(['school_id' => $this->school->id, 'course_id' => $course->id, 'academic_year' => 1, 'semester' => 1, 'units' => 3]);
    $enrollment = StudentEnrollment::factory()->create(['student_id' => $student->id, 'school_id' => $this->school->id, 'course_id' => $course->id, 'school_year' => $year, 'semester' => 1]);
    $subjectEnrollment = SubjectEnrollment::factory()->create([
        'student_id' => $student->id, 'enrollment_id' => $enrollment->id, 'subject_id' => $subject->id,
        'school_id' => $this->school->id, 'school_year' => $year, 'semester' => 1,
        'grade' => 87, 'grade_symbol' => null, 'grade_outcome' => 'pass', 'remarks' => 'Passed',
    ]);
    $user->createToken('Student Agent', ['mcp:read']);
    $service = app(App\Services\GradeReleaseService::class);
    $service->publish($student, $year, 1, $this->admin);

    KoAkademyServer::actingAs($user)->tool(GetStudentChecklistTool::class, [])
        ->assertOk()->assertStructuredContent(fn ($json) => $json->where('checklist.0.grade', null)->where('checklist.0.grade_outcome', null)
        ->where('checklist.0.status', 'In Progress')->where('academic_summary.cumulative_gwa', null)->etc());
    KoAkademyServer::actingAs($user)->tool(GetAvailableSubjectsTool::class, ['year_level' => 1, 'semester' => 1])
        ->assertOk()->assertStructuredContent(fn ($json) => $json->where('subjects.0.status', 'currently_enrolled')->etc());
    $this->actingAs($user);
    $ai = json_decode(app(GetCurriculumProgressTool::class)->handle(new AiRequest([])), true);
    expect($ai['checklist'][0]['grade'])->toBeNull()
        ->and($ai['academic_summary']['cumulative_gwa'])->toBeNull();

    $service->updateClearance($student, $year, 1, true, null, $this->admin);
    KoAkademyServer::actingAs($user)->tool(GetStudentChecklistTool::class, [])
        ->assertOk()->assertStructuredContent(fn ($json) => $json->where('checklist.0.grade', '87.00')->where('checklist.0.grade_outcome', 'pass')->etc());
    $subjectEnrollment->update(['grade' => 92]);
    KoAkademyServer::actingAs($user)->tool(GetStudentChecklistTool::class, [])
        ->assertOk()->assertStructuredContent(fn ($json) => $json->where('checklist.0.grade', '87.00')->etc());
    expect((float) $subjectEnrollment->fresh()->grade)->toBe(92.0);
});

it('evaluates student curriculum checklist and computes real GWA and deficiencies via MCP', function (): void {
    $dept = Department::factory()->create();
    $course = Course::factory()->create([
        'department_id' => $dept->id,
        'code' => 'BSCS',
        'units' => 120,
    ]);

    $student = Student::factory()->create([
        'school_id' => $this->school->id,
        'institution_id' => $this->school->id,
        'course_id' => $course->id,
        'academic_year' => 2,
    ]);

    // Create 3 curriculum subjects:
    // Subj 1: 3 units, passed with 1.25
    $subj1 = Subject::factory()->create([
        'course_id' => $course->id,
        'code' => 'CS101',
        'title' => 'Intro to Programming',
        'units' => 3,
        'academic_year' => 1,
        'semester' => 1,
    ]);

    // Subj 2: 3 units, passed with 1.75
    $subj2 = Subject::factory()->create([
        'course_id' => $course->id,
        'code' => 'CS102',
        'title' => 'Data Structures',
        'units' => 3,
        'academic_year' => 1,
        'semester' => 2,
        'pre_riquisite' => 'CS101',
    ]);

    // Subj 3: 3 units, deficient / not yet taken
    $subj3 = Subject::factory()->create([
        'course_id' => $course->id,
        'code' => 'CS201',
        'title' => 'Algorithms',
        'units' => 3,
        'academic_year' => 2,
        'semester' => 1,
        'pre_riquisite' => 'CS102',
    ]);

    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => (string) $student->id,
        'school_id' => $this->school->id,
        'course_id' => $course->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
    ]);

    SubjectEnrollment::factory()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'subject_id' => $subj1->id,
        'grade' => 1.25,
        'grade_outcome' => 'pass',
        'school_id' => $this->school->id,
    ]);

    SubjectEnrollment::factory()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'subject_id' => $subj2->id,
        'grade' => 1.75,
        'grade_outcome' => 'pass',
        'school_id' => $this->school->id,
    ]);

    $response = KoAkademyServer::actingAs($this->admin)
        ->tool(GetStudentChecklistTool::class, [
            'student_id' => $student->id,
        ]);

    $response->assertOk()
        ->assertStructuredContent(function ($json) use ($student): void {
            // (1.25 * 3 + 1.75 * 3) / 6 = (3.75 + 5.25) / 6 = 9.0 / 6 = 1.50 GWA
            $json->where('student.id', $student->id)
                ->where('academic_summary.total_curriculum_units', 9)
                ->where('academic_summary.completed_units', 6)
                ->where('academic_summary.remaining_units', 3)
                ->where('academic_summary.cumulative_gwa', 1.5)
                ->where('academic_summary.completed_subjects_count', 2)
                ->where('academic_summary.deficient_subjects_count', 1)
                ->where('deficiencies.0.code', 'CS201')
                ->where('checklist_items_count', 3)
                ->etc();
        });
});

it('evaluates student curriculum checklist and detects unpassed prerequisites and deficiencies via AI tool', function (): void {
    $dept = Department::factory()->create();
    $course = Course::factory()->create([
        'department_id' => $dept->id,
        'code' => 'BSCS',
        'units' => 120,
    ]);

    $student = Student::factory()->create([
        'school_id' => $this->school->id,
        'institution_id' => $this->school->id,
        'course_id' => $course->id,
    ]);

    // Subj 1: failed with 5.0
    $subj1 = Subject::factory()->create([
        'course_id' => $course->id,
        'code' => 'MATH101',
        'title' => 'Calculus 1',
        'units' => 4,
        'academic_year' => 1,
        'semester' => 1,
    ]);

    // Subj 2: dependent on MATH101
    $subj2 = Subject::factory()->create([
        'course_id' => $course->id,
        'code' => 'MATH102',
        'title' => 'Calculus 2',
        'units' => 4,
        'academic_year' => 1,
        'semester' => 2,
        'pre_riquisite' => 'MATH101',
    ]);

    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => (string) $student->id,
        'school_id' => $this->school->id,
        'course_id' => $course->id,
    ]);

    SubjectEnrollment::factory()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'subject_id' => $subj1->id,
        'grade' => 5.0,
        'grade_outcome' => 'fail',
        'school_id' => $this->school->id,
    ]);

    $this->actingAs($this->admin);

    $aiTool = new GetCurriculumProgressTool;
    $rawResult = $aiTool->handle(new AiRequest([
        'student_id' => (string) $student->id,
    ]));

    $result = json_decode((string) $rawResult, true);

    expect($result)->toHaveKey('academic_summary')
        ->and($result['academic_summary']['failed_units'])->toBe(4)
        ->and($result['failed_retakes_needed'])->toHaveCount(1)
        ->and($result['failed_retakes_needed'][0]['code'])->toBe('MATH101')
        ->and($result['prerequisite_blockers'])->toHaveCount(1)
        ->and($result['prerequisite_blockers'][0]['subject_code'])->toBe('MATH102')
        ->and($result['prerequisite_blockers'][0]['unmet_prerequisite'])->toBe('MATH101');
});
