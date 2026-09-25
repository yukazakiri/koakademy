<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Mcp\Servers\KoAkademyServer;
use App\Mcp\Tools\AdvanceEnrollmentStepTool;
use App\Mcp\Tools\DropStudentSubjectEnrollmentTool;
use App\Mcp\Tools\EnrollStudentSubjectTool;
use App\Mcp\Tools\GetAvailableSubjectsTool;
use App\Mcp\Tools\GetCourseCurriculumTool;
use App\Mcp\Tools\GetEnrollmentAuditTrailTool;
use App\Mcp\Tools\GetEnrollmentStatusTool;
use App\Mcp\Tools\GetMyContextTool;
use App\Mcp\Tools\GetSchoolDetailsTool;
use App\Mcp\Tools\GetSchoolMetricsTool;
use App\Mcp\Tools\GetStatementOfAccountTool;
use App\Mcp\Tools\GetStudentProfileTool;
use App\Mcp\Tools\GetStudentScheduleTool;
use App\Mcp\Tools\GetStudentSubjectEnrollmentsTool;
use App\Mcp\Tools\ListAcademicOfferingsTool;
use App\Mcp\Tools\ListPendingEnrollmentsTool;
use App\Mcp\Tools\ListStudentEnrollmentsTool;
use App\Mcp\Tools\SearchFacultyTool;
use App\Mcp\Tools\SearchStudentsTool;
use App\Mcp\Tools\UpdateEnrollmentRemarksTool;
use App\Mcp\Tools\UpdateSubjectEnrollmentGradeTool;
use App\Mcp\Tools\VerifyEnrollmentRequirementTool;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Department;
use App\Models\EnrollmentRequirement;
use App\Models\Faculty;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\User;
use App\Services\GeneralSettingsService;
use App\Services\TenantContext;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    config(['activitylog.enabled' => false]);
    config(['api.mcp.enabled' => true]);

    $this->school = School::factory()->create();

    $this->settings = GeneralSetting::create([
        'school_starting_date' => now()->startOfYear(),
        'school_ending_date' => now()->startOfYear()->addYear(),
        'semester' => 1,
        'site_name' => 'KoAkademy MCP Test School',
    ]);

    $this->staff = User::factory()->create([
        'role' => UserRole::AdministrativeAssistant,
        'school_id' => $this->school->id,
    ]);

    $this->admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => $this->school->id,
    ]);

    app(TenantContext::class)->setCurrentSchool($this->school);

    $permissions = [
        'ViewAny:Student',
        'View:Student',
        'ViewAny:StudentEnrollment',
        'View:StudentEnrollment',
        'Update:StudentEnrollment',
        'ViewAny:Course',
        'View:Course',
        'ViewAny:Classes',
        'View:Classes',
        'View:Cashier',
        'view_tuition_fees',
        'ViewAny:Faculty',
        'View:Faculty',
        'ViewAny:ClassSchedule',
        'Update:Student',
        'Update:Subject',
        'View:Subject',
        'Update:Classes',
        'View:Room',
        'Update:Room',
        'Create:Student',
        'Create:Subject',
        'Delete:Subject',
        'Create:Classes',
        'Delete:Classes',
        'Create:Room',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
});

it('registers all core mcp tools on the server', function (): void {
    KoAkademyServer::tools()
        ->assertRegistered([
            GetMyContextTool::class,
            GetSchoolDetailsTool::class,
            GetSchoolMetricsTool::class,
            SearchStudentsTool::class,
            GetStudentProfileTool::class,
            GetStudentScheduleTool::class,
            SearchFacultyTool::class,
            ListStudentEnrollmentsTool::class,
            GetEnrollmentStatusTool::class,
            ListPendingEnrollmentsTool::class,
            GetEnrollmentAuditTrailTool::class,
            GetCourseCurriculumTool::class,
            GetAvailableSubjectsTool::class,
            GetStudentSubjectEnrollmentsTool::class,
            ListAcademicOfferingsTool::class,
            GetStatementOfAccountTool::class,
            AdvanceEnrollmentStepTool::class,
            VerifyEnrollmentRequirementTool::class,
            UpdateEnrollmentRemarksTool::class,
            EnrollStudentSubjectTool::class,
            UpdateSubjectEnrollmentGradeTool::class,
            DropStudentSubjectEnrollmentTool::class,
            App\Mcp\Tools\QueryTimetableScheduleTool::class,
            App\Mcp\Tools\ManageStudentTool::class,
            App\Mcp\Tools\ManageCurriculumSubjectTool::class,
            App\Mcp\Tools\ManageClassScheduleTool::class,
            App\Mcp\Tools\ManageRoomTool::class,
        ]);
});

it('returns caller context and academic period for an authorized staff member', function (): void {
    $token = $this->staff->createToken('Staff Agent', ['mcp:read']);

    $response = KoAkademyServer::actingAs($this->staff)
        ->tool(GetMyContextTool::class, []);

    $response->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('user.id', $this->staff->id)
                ->where('school.id', $this->school->id)
                ->where('school.name', $this->school->name)
                ->where('academic_period.semester', 1)
                ->where('mcp.token_name', 'Staff Agent')
                ->where('mcp.can_write', false)
                ->etc();
        });
});

it('rejects an mcp call when the token lacks mcp:read ability', function (): void {
    $this->staff->createToken('Legacy API Key', ['read']);

    $response = KoAkademyServer::actingAs($this->staff)
        ->tool(GetMyContextTool::class, []);

    $response->assertHasErrors(['The API key does not have mcp:read access.']);
});

it('rejects wildcard tokens for mcp calls', function (): void {
    $this->staff->createToken('Wildcard API Key', ['*']);

    $response = KoAkademyServer::actingAs($this->staff)
        ->tool(GetMyContextTool::class, []);

    $response->assertHasErrors(['The API key does not have mcp:read access.']);
});

it('searches students within the authorized school and enforces permissions', function (): void {
    $this->staff->createToken('Staff Agent', ['mcp:read']);

    $student = Student::factory()->create([
        'school_id' => $this->school->id,
        'institution_id' => $this->school->id,
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'student_id' => 202601,
        'email' => 'maria.santos@student.koakademy.edu',
    ]);

    $otherSchool = School::factory()->create();
    Student::factory()->create([
        'school_id' => $otherSchool->id,
        'institution_id' => $otherSchool->id,
        'first_name' => 'Maria',
        'last_name' => 'Clara',
        'student_id' => 202602,
        'email' => 'maria.clara@other.koakademy.edu',
    ]);

    $unpermittedResponse = KoAkademyServer::actingAs($this->staff)
        ->tool(SearchStudentsTool::class, ['query' => 'Maria']);

    $unpermittedResponse->assertHasErrors(['You are not permitted to search student records.']);

    $this->staff->givePermissionTo('ViewAny:Student');

    $response = KoAkademyServer::actingAs($this->staff)
        ->tool(SearchStudentsTool::class, ['query' => 'Maria']);

    $response->assertOk()
        ->assertStructuredContent(function ($json) use ($student): void {
            $json->where('count', 1)
                ->where('students.0.id', $student->id)
                ->where('students.0.student_number', '202601')
                ->where('students.0.name', $student->full_name)
                ->etc();
        });
});

it('returns student schedule and detected conflict details', function (): void {
    $this->staff->createToken('Staff Agent', ['mcp:read']);
    $this->staff->givePermissionTo('View:Student');

    $student = Student::factory()->create([
        'school_id' => $this->school->id,
        'institution_id' => $this->school->id,
    ]);

    $response = KoAkademyServer::actingAs($this->staff)
        ->tool(GetStudentScheduleTool::class, ['student_id' => $student->id]);

    $response->assertOk()
        ->assertStructuredContent(function ($json) use ($student): void {
            $json->where('student.id', $student->id)
                ->has('classes')
                ->has('conflicts')
                ->etc();
        });
});

it('returns enrollment workflow status and requirements', function (): void {
    $this->staff->createToken('Staff Agent', ['mcp:read']);
    $this->staff->givePermissionTo('View:StudentEnrollment');

    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $course = Course::factory()->create(['school_id' => $this->school->id]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_id' => $this->school->id,
        'status' => 'submitted',
        'workflow_runtime' => StudentEnrollment::WorkflowRuntimePolicyV1,
        'current_step_key' => 'submitted',
    ]);

    $enrollment->requirements()->create([
        'requirement_key' => 'form_138',
        'label' => 'Form 138 (Report Card)',
        'is_required' => true,
        'status' => EnrollmentRequirement::Pending,
    ]);

    $response = KoAkademyServer::actingAs($this->staff)
        ->tool(GetEnrollmentStatusTool::class, ['enrollment_id' => $enrollment->id]);

    $response->assertOk()
        ->assertStructuredContent(function ($json) use ($enrollment): void {
            $json->where('id', $enrollment->id)
                ->where('status', 'submitted')
                ->where('current_step_key', 'submitted')
                ->where('requirements.0.key', 'form_138')
                ->where('requirements.0.status', 'pending')
                ->etc();
        });
});

it('lists courses and classes for the active school and academic period', function (): void {
    $this->staff->createToken('Staff Agent', ['mcp:read']);
    $this->staff->givePermissionTo(['ViewAny:Course', 'ViewAny:Classes']);

    Course::factory()->create([
        'school_id' => $this->school->id,
        'code' => 'CS101',
        'title' => 'Introduction to Computing',
        'is_active' => true,
    ]);

    $response = KoAkademyServer::actingAs($this->staff)
        ->tool(ListAcademicOfferingsTool::class, []);

    $response->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('school.id', $this->school->id)
                ->where('courses.0.code', 'CS101')
                ->etc();
        });
});

it('gates statement of account behind finance permissions', function (): void {
    $this->staff->createToken('Staff Agent', ['mcp:read']);

    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'school_id' => $this->school->id,
    ]);

    StudentTuition::query()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'academic_year' => 1,
        'total_tuition' => 15000.00,
        'total_balance' => 10000.00,
        'total_lectures' => 12000.00,
        'total_laboratory' => 3000.00,
        'total_miscelaneous_fees' => 1500.00,
        'downpayment' => 5000.00,
        'overall_tuition' => 16500.00,
        'paid' => 5000.00,
        'status' => 'Downpayment',
        'school_year' => '2026 - 2027',
        'semester' => 1,
    ]);

    $denied = KoAkademyServer::actingAs($this->staff)
        ->tool(GetStatementOfAccountTool::class, ['enrollment_id' => $enrollment->id]);

    $denied->assertHasErrors(['You are not permitted to view statements of account.']);

    $this->staff->givePermissionTo('View:Cashier');

    $allowed = KoAkademyServer::actingAs($this->staff)
        ->tool(GetStatementOfAccountTool::class, ['enrollment_id' => $enrollment->id]);

    $allowed->assertOk()
        ->assertStructuredContent(function ($json) use ($enrollment): void {
            $json->where('enrollment_id', $enrollment->id)
                ->where('statement_available', true)
                ->where('summary.total_tuition', 15000)
                ->where('summary.total_balance', 11500)
                ->etc();
        });
});

it('verifies an enrollment requirement with mcp:write and domain permissions', function (): void {
    $readOnlyToken = $this->staff->createToken('Read Only Agent', ['mcp:read']);
    $this->staff->givePermissionTo('Update:StudentEnrollment');

    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'school_id' => $this->school->id,
    ]);

    $requirement = $enrollment->requirements()->create([
        'requirement_key' => 'birth_certificate',
        'label' => 'PSA Birth Certificate',
        'is_required' => true,
        'status' => EnrollmentRequirement::Pending,
    ]);

    $deniedWrite = KoAkademyServer::actingAs($this->staff)
        ->tool(VerifyEnrollmentRequirementTool::class, [
            'requirement_id' => $requirement->id,
            'idempotency_key' => 'mcp-write-test-1',
        ]);

    $deniedWrite->assertHasErrors(['The API key does not have mcp:write access.']);

    $this->staff->tokens()->delete();
    $writeToken = $this->staff->createToken('Write Agent', ['mcp:read', 'mcp:write']);
    $this->staff->withAccessToken($writeToken->accessToken);

    $verified = KoAkademyServer::actingAs($this->staff)
        ->tool(VerifyEnrollmentRequirementTool::class, [
            'requirement_id' => $requirement->id,
            'idempotency_key' => 'mcp-write-test-1',
        ]);

    $verified->assertOk()
        ->assertStructuredContent(function ($json) use ($requirement): void {
            $json->where('id', $requirement->id)
                ->where('status', 'verified')
                ->where('verified_by', $this->staff->id)
                ->where('idempotency_key', 'mcp-write-test-1')
                ->etc();
        });

    expect($requirement->refresh()->status)->toBe(EnrollmentRequirement::Verified)
        ->and($requirement->verified_by)->toBe($this->staff->id);

    // Replay with identical idempotency key is safe
    $replayed = KoAkademyServer::actingAs($this->staff)
        ->tool(VerifyEnrollmentRequirementTool::class, [
            'requirement_id' => $requirement->id,
            'idempotency_key' => 'mcp-write-test-1',
        ]);

    $replayed->assertOk();
});

it('blocks mcp mutations when mcp_write_enabled is toggled off in settings', function (): void {
    $this->staff->createToken('Write Agent', ['mcp:read', 'mcp:write']);
    $this->staff->givePermissionTo('Update:StudentEnrollment');

    app(GeneralSettingsService::class)->updateApiManagementConfig([
        'public_api_enabled' => true,
        'public_settings_enabled' => true,
        'public_settings_fields' => ['site_name'],
        'mcp_enabled' => true,
        'mcp_write_enabled' => false,
    ]);

    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'school_id' => $this->school->id,
    ]);

    $requirement = $enrollment->requirements()->create([
        'requirement_key' => 'good_moral',
        'label' => 'Good Moral Certificate',
        'is_required' => true,
        'status' => EnrollmentRequirement::Pending,
    ]);

    $response = KoAkademyServer::actingAs($this->staff)
        ->tool(VerifyEnrollmentRequirementTool::class, [
            'requirement_id' => $requirement->id,
            'idempotency_key' => 'mcp-blocked-write-1',
        ]);

    $response->assertHasErrors(['MCP data modifications are disabled in system settings.']);
});

it('reports effective write capability when the global write switch is disabled', function (): void {
    $this->staff->createToken('Write Agent', ['mcp:read', 'mcp:write']);

    app(GeneralSettingsService::class)->updateApiManagementConfig([
        'public_api_enabled' => true,
        'public_settings_enabled' => true,
        'public_settings_fields' => ['site_name'],
        'mcp_enabled' => true,
        'mcp_write_enabled' => false,
    ]);

    KoAkademyServer::actingAs($this->staff)
        ->tool(GetMyContextTool::class, [])
        ->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('mcp.can_write', false)->etc();
        });
});

it('serves http mcp requests with bearer authentication and tenant headers', function (): void {
    $token = $this->staff->createToken('Staff Bearer', ['mcp:read'])->plainTextToken;

    $response = $this->withToken($token)
        ->withHeader('X-KoAkademy-School', (string) $this->school->id)
        ->postJson('/mcp/koakademy', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => [],
        ]);

    $response->assertOk()
        ->assertJsonPath('jsonrpc', '2.0')
        ->assertJsonPath('id', 1)
        ->assertJsonStructure([
            'result' => [
                'tools',
            ],
        ]);
});

it('returns 401 with www-authenticate when unauthenticated', function (): void {
    $response = $this->postJson('/mcp/koakademy', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ]);

    $response->assertUnauthorized()
        ->assertHeader('WWW-Authenticate');
});

it('returns 404 when mcp is disabled in settings', function (): void {
    app(GeneralSettingsService::class)->updateApiManagementConfig([
        'public_api_enabled' => true,
        'public_settings_enabled' => true,
        'public_settings_fields' => ['site_name'],
        'mcp_enabled' => false,
    ]);

    $token = $this->staff->createToken('Staff Bearer', ['mcp:read'])->plainTextToken;

    $response = $this->withToken($token)
        ->withHeader('X-KoAkademy-School', (string) $this->school->id)
        ->postJson('/mcp/koakademy', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ]);

    $response->assertNotFound();
});

it('returns school details with active departments and capabilities', function (): void {
    $this->staff->createToken('Staff Agent', ['mcp:read']);

    Department::factory()->create([
        'school_id' => $this->school->id,
        'name' => 'Department of Computer Studies',
        'code' => 'DCS',
        'is_active' => true,
    ]);

    $response = KoAkademyServer::actingAs($this->staff)
        ->tool(GetSchoolDetailsTool::class, []);

    $response->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('id', $this->school->id)
                ->where('name', $this->school->name)
                ->where('departments.0.code', 'DCS')
                ->has('curriculum_capabilities')
                ->etc();
        });
});

it('restricts school metrics tool to administrators', function (): void {
    $instructor = User::factory()->create([
        'role' => UserRole::Instructor,
        'school_id' => $this->school->id,
    ]);
    $instructor->createToken('Instructor Agent', ['mcp:read']);

    $denied = KoAkademyServer::actingAs($instructor)
        ->tool(GetSchoolMetricsTool::class, []);

    $denied->assertHasErrors(['This tool is restricted to administrators.']);

    $this->admin->createToken('Admin Agent', ['mcp:read']);

    Student::factory()->create(['school_id' => $this->school->id, 'status' => 'enrolled']);
    Student::factory()->create(['school_id' => $this->school->id, 'status' => 'applicant']);

    $allowed = KoAkademyServer::actingAs($this->admin)
        ->tool(GetSchoolMetricsTool::class, []);

    $allowed->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('school.id', $this->school->id)
                ->where('metrics.total_students', 2)
                ->where('metrics.enrolled_students', 1)
                ->where('metrics.applicants', 1)
                ->etc();
        });
});

it('lists pending enrollments for administrators', function (): void {
    $this->admin->createToken('Admin Agent', ['mcp:read']);
    $this->admin->givePermissionTo('ViewAny:StudentEnrollment');

    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $course = Course::factory()->create(['school_id' => $this->school->id]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_id' => $this->school->id,
        'status' => 'submitted',
        'school_year' => app(GeneralSettingsService::class)->getCurrentSchoolYearString(),
        'semester' => 1,
    ]);

    $response = KoAkademyServer::actingAs($this->admin)
        ->tool(ListPendingEnrollmentsTool::class, []);

    $response->assertOk()
        ->assertStructuredContent(function ($json) use ($enrollment): void {
            $json->where('count', 1)
                ->where('enrollments.0.id', $enrollment->id)
                ->where('enrollments.0.status', 'submitted')
                ->etc();
        });
});

it('searches faculty members with ViewAny:Faculty permission', function (): void {
    $this->staff->createToken('Staff Agent', ['mcp:read']);

    $denied = KoAkademyServer::actingAs($this->staff)
        ->tool(SearchFacultyTool::class, ['query' => 'Cruz']);

    $denied->assertHasErrors(['You are not permitted to search faculty records.']);

    $this->staff->givePermissionTo('ViewAny:Faculty');

    $faculty = Faculty::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Juan',
        'last_name' => 'Cruz',
        'faculty_id_number' => 'FAC-901',
        'department' => 'Engineering',
        'position' => 'Assistant Professor',
    ]);

    $allowed = KoAkademyServer::actingAs($this->staff)
        ->tool(SearchFacultyTool::class, ['query' => 'Cruz']);

    $allowed->assertOk()
        ->assertStructuredContent(function ($json) use ($faculty): void {
            $json->where('count', 1)
                ->where('faculty.0.id', $faculty->id)
                ->where('faculty.0.name', $faculty->full_name)
                ->where('faculty.0.faculty_id_number', 'FAC-901')
                ->etc();
        });
});

it('advances enrollment workflow step for administrators with mcp:write', function (): void {
    Illuminate\Support\Facades\Notification::fake();
    $this->admin->givePermissionTo('Update:StudentEnrollment');

    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $course = Course::factory()->create(['school_id' => $this->school->id]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_id' => $this->school->id,
        'status' => app(App\Services\EnrollmentPipelineService::class)->getPendingStatus(),
        'workflow_runtime' => StudentEnrollment::WorkflowRuntimeLegacy,
    ]);

    $this->admin->createToken('Admin Read Only', ['mcp:read']);

    $denied = KoAkademyServer::actingAs($this->admin)
        ->tool(AdvanceEnrollmentStepTool::class, [
            'enrollment_id' => $enrollment->id,
            'idempotency_key' => 'adv-step-1',
        ]);

    $denied->assertHasErrors(['The API key does not have mcp:write access.']);

    $this->admin->tokens()->delete();
    $writeToken = $this->admin->createToken('Admin Write', ['mcp:read', 'mcp:write']);
    $this->admin->withAccessToken($writeToken->accessToken);

    $allowed = KoAkademyServer::actingAs($this->admin)
        ->tool(AdvanceEnrollmentStepTool::class, [
            'enrollment_id' => $enrollment->id,
            'reason' => 'Documents verified by administrator',
            'idempotency_key' => 'adv-step-1',
        ]);

    $allowed->assertOk()
        ->assertStructuredContent(function ($json) use ($enrollment): void {
            $json->where('enrollment_id', $enrollment->id)
                ->where('successful', true)
                ->where('idempotency_key', 'adv-step-1')
                ->etc();
        });

    $replayed = KoAkademyServer::actingAs($this->admin)
        ->tool(AdvanceEnrollmentStepTool::class, [
            'enrollment_id' => $enrollment->id,
            'reason' => 'Documents verified by administrator',
            'idempotency_key' => 'adv-step-1',
        ]);

    $replayed->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('successful', true)
                ->where('idempotency_key', 'adv-step-1')
                ->where('message', 'This transition attempt was already processed.')
                ->etc();
        });
});

it('updates enrollment remarks with mcp:write and domain permissions', function (): void {
    $writeToken = $this->staff->createToken('Staff Write', ['mcp:read', 'mcp:write']);
    $this->staff->withAccessToken($writeToken->accessToken);
    $this->staff->givePermissionTo('Update:StudentEnrollment');

    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'school_id' => $this->school->id,
        'remarks' => 'Initial note',
    ]);

    $response = KoAkademyServer::actingAs($this->staff)
        ->tool(UpdateEnrollmentRemarksTool::class, [
            'enrollment_id' => $enrollment->id,
            'remarks' => 'Follow up on original birth certificate by Friday.',
        ]);

    $response->assertOk()
        ->assertStructuredContent(function ($json) use ($enrollment): void {
            $json->where('enrollment_id', $enrollment->id)
                ->where('remarks', 'Follow up on original birth certificate by Friday.')
                ->etc();
        });

    expect($enrollment->refresh()->remarks)->toBe('Follow up on original birth certificate by Friday.');
});

it('retrieves enrollment audit trail of workflow events', function (): void {
    $this->staff->createToken('Staff Agent', ['mcp:read']);
    $this->staff->givePermissionTo('View:StudentEnrollment');

    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'school_id' => $this->school->id,
    ]);

    App\Models\EnrollmentWorkflowEvent::query()->create([
        'student_enrollment_id' => $enrollment->id,
        'actor_id' => $this->staff->id,
        'event_type' => 'step_transition',
        'from_step_key' => 'submitted',
        'to_step_key' => 'academic_verified',
        'status' => 'academic_verified',
        'reason' => 'Form 138 validated',
        'idempotency_key' => 'audit-trail-event-1',
    ]);

    $response = KoAkademyServer::actingAs($this->staff)
        ->tool(GetEnrollmentAuditTrailTool::class, ['enrollment_id' => $enrollment->id]);

    $response->assertOk()
        ->assertStructuredContent(function ($json) use ($enrollment): void {
            $json->where('enrollment_id', $enrollment->id)
                ->where('count', 1)
                ->where('events.0.event_type', 'step_transition')
                ->where('events.0.reason', 'Form 138 validated')
                ->where('events.0.actor.id', $this->staff->id)
                ->etc();
        });
});

it('retrieves detailed student profile for authorized staff', function (): void {
    $this->staff->createToken('Staff Agent', ['mcp:read']);
    $this->staff->givePermissionTo('View:Student');

    $course = Course::factory()->create(['school_id' => $this->school->id, 'code' => 'BSCS', 'title' => 'Computer Science']);
    $student = Student::factory()->create([
        'school_id' => $this->school->id,
        'institution_id' => $this->school->id,
        'first_name' => 'Eduardo',
        'last_name' => 'Reyes',
        'student_id' => 202611,
        'course_id' => $course->id,
        'status' => 'enrolled',
    ]);

    $response = KoAkademyServer::actingAs($this->staff)
        ->tool(GetStudentProfileTool::class, ['student_id' => $student->id]);

    $response->assertOk()
        ->assertStructuredContent(function ($json) use ($student): void {
            $json->where('id', $student->id)
                ->where('student_number', '202611')
                ->where('first_name', 'Eduardo')
                ->where('last_name', 'Reyes')
                ->where('course.code', 'BSCS')
                ->where('status', 'enrolled')
                ->etc();
        });
});

it('allows student to retrieve their own profile without student_id argument', function (): void {
    $studentUser = User::factory()->create([
        'role' => UserRole::Student,
        'school_id' => $this->school->id,
    ]);
    $studentRecord = Student::factory()->create([
        'user_id' => $studentUser->id,
        'school_id' => $this->school->id,
        'institution_id' => $this->school->id,
        'first_name' => 'Ana',
        'last_name' => 'Cruz',
        'student_id' => 202612,
    ]);

    $studentUser->createToken('Student Agent', ['mcp:read']);

    $response = KoAkademyServer::actingAs($studentUser)
        ->tool(GetStudentProfileTool::class, []);

    $response->assertOk()
        ->assertStructuredContent(function ($json) use ($studentRecord): void {
            $json->where('id', $studentRecord->id)
                ->where('student_number', '202612')
                ->where('first_name', 'Ana')
                ->where('last_name', 'Cruz')
                ->etc();
        });
});

it('lists all student enrollments across academic history', function (): void {
    $this->staff->createToken('Staff Agent', ['mcp:read']);
    $this->staff->givePermissionTo('View:StudentEnrollment');

    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $course = Course::factory()->create(['school_id' => $this->school->id]);

    $enrollment1 = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_id' => $this->school->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'status' => 'completed',
    ]);

    $enrollment2 = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_id' => $this->school->id,
        'school_year' => '2025 - 2026',
        'semester' => 2,
        'status' => 'enrolled',
    ]);

    $response = KoAkademyServer::actingAs($this->staff)
        ->tool(ListStudentEnrollmentsTool::class, ['student_id' => $student->id]);

    $response->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('count', 2)
                ->has('enrollments')
                ->etc();
        });
});

it('retrieves complete course curriculum broken down by term', function (): void {
    $this->staff->createToken('Staff Agent', ['mcp:read']);
    $this->staff->givePermissionTo('View:Course');

    $course = Course::factory()->create(['school_id' => $this->school->id, 'code' => 'BSIT', 'title' => 'Information Technology']);

    $subject1 = Subject::factory()->create([
        'course_id' => $course->id,
        'code' => 'IT111',
        'title' => 'Intro to IT',
        'units' => 3,
        'academic_year' => 1,
        'semester' => 1,
    ]);

    $subject2 = Subject::factory()->create([
        'course_id' => $course->id,
        'code' => 'IT121',
        'title' => 'Computer Programming 1',
        'units' => 3,
        'academic_year' => 1,
        'semester' => 2,
        'pre_riquisite' => ['IT111'],
    ]);

    $response = KoAkademyServer::actingAs($this->staff)
        ->tool(GetCourseCurriculumTool::class, [
            'course_id' => $course->id,
            'year_level' => 1,
        ]);

    $response->assertOk()
        ->assertStructuredContent(function ($json) use ($course): void {
            $json->where('course.id', $course->id)
                ->where('course.code', 'BSIT')
                ->where('subjects_count', 2)
                ->where('subjects.0.code', 'IT111')
                ->where('subjects.1.code', 'IT121')
                ->where('subjects.1.prerequisites.0', 'IT111')
                ->etc();
        });
});

it('identifies available subjects for a student term based on course curriculum', function (): void {
    $this->staff->createToken('Staff Agent', ['mcp:read']);
    $this->staff->givePermissionTo('View:Student');

    $course = Course::factory()->create(['school_id' => $this->school->id]);
    $student = Student::factory()->create([
        'school_id' => $this->school->id,
        'course_id' => $course->id,
        'academic_year' => 1,
    ]);

    $subject1 = Subject::factory()->create([
        'course_id' => $course->id,
        'code' => 'ENG101',
        'title' => 'Communication Skills',
        'units' => 3,
        'academic_year' => 1,
        'semester' => 1,
    ]);

    $subject2 = Subject::factory()->create([
        'course_id' => $course->id,
        'code' => 'MATH101',
        'title' => 'College Algebra',
        'units' => 3,
        'academic_year' => 1,
        'semester' => 1,
    ]);

    $subject3 = Subject::factory()->create([
        'course_id' => $course->id,
        'code' => 'MATH102',
        'title' => 'Advanced Algebra',
        'units' => 3,
        'academic_year' => 1,
        'semester' => 1,
        'pre_riquisite' => ['MATH101'],
    ]);

    // Student has already completed ENG101 with grade 1.75
    $priorEnrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_id' => $this->school->id,
    ]);

    SubjectEnrollment::factory()->create([
        'student_id' => $student->id,
        'subject_id' => $subject1->id,
        'enrollment_id' => $priorEnrollment->id,
        'grade' => 1.75,
        'remarks' => 'Passed',
        'school_id' => $this->school->id,
    ]);

    $response = KoAkademyServer::actingAs($this->staff)
        ->tool(GetAvailableSubjectsTool::class, [
            'student_id' => $student->id,
            'year_level' => 1,
            'semester' => 1,
        ]);

    $response->assertOk()
        ->assertStructuredContent(function ($json) use ($subject1, $subject2, $subject3): void {
            $json->where('subjects_count', 3)
                ->where('available_count', 1)
                ->where('prerequisites_unfulfilled_count', 1)
                ->where('subjects.0.id', $subject1->id)
                ->where('subjects.0.status', 'completed')
                ->where('subjects.0.last_grade', 1.75)
                ->where('subjects.1.id', $subject2->id)
                ->where('subjects.1.status', 'available')
                ->where('subjects.2.id', $subject3->id)
                ->where('subjects.2.status', 'prerequisites_unfulfilled')
                ->where('subjects.2.unmet_prerequisites.0', 'MATH101')
                ->etc();
        });
});

it('retrieves student subject enrollments with grades and instructor details', function (): void {
    $this->staff->createToken('Staff Agent', ['mcp:read']);
    $this->staff->givePermissionTo('View:StudentEnrollment');

    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'school_id' => $this->school->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
    ]);

    $subject = Subject::factory()->create(['code' => 'PHY101', 'title' => 'General Physics', 'units' => 3]);

    SubjectEnrollment::factory()->create([
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'enrollment_id' => $enrollment->id,
        'school_id' => $this->school->id,
        'section' => 'A',
        'grade' => 1.5,
        'remarks' => 'Passed',
        'instructor' => 'Prof. Mendoza',
    ]);

    $response = KoAkademyServer::actingAs($this->staff)
        ->tool(GetStudentSubjectEnrollmentsTool::class, ['enrollment_id' => $enrollment->id]);

    $response->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('subjects_count', 1)
                ->where('total_units', 3)
                ->where('subjects.0.subject_code', 'PHY101')
                ->where('subjects.0.grade', 1.5)
                ->where('subjects.0.remarks', 'Passed')
                ->where('subjects.0.instructor', 'Prof. Mendoza')
                ->etc();
        });
});

it('enrolls a student in a subject under an enrollment with idempotency and validates curriculum and classes', function (): void {
    $writeToken = $this->staff->createToken('Staff Write', ['mcp:read', 'mcp:write']);
    $this->staff->withAccessToken($writeToken->accessToken);
    $this->staff->givePermissionTo('Update:StudentEnrollment');

    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $course = Course::factory()->create([
        'school_id' => $this->school->id,
        'lec_per_unit' => 500,
        'lab_per_unit' => 1000,
    ]);

    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_id' => $this->school->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
    ]);

    $subject = Subject::factory()->create([
        'course_id' => $course->id,
        'code' => 'HIST101',
        'title' => 'Philippine History',
        'units' => 3,
        'lecture' => 3,
        'laboratory' => 0,
    ]);

    // Reject subjects outside enrollment program
    $otherCourse = Course::factory()->create(['school_id' => $this->school->id]);
    $unrelatedSubject = Subject::factory()->create([
        'course_id' => $otherCourse->id,
        'code' => 'NURS101',
    ]);

    $deniedProgram = KoAkademyServer::actingAs($this->staff)
        ->tool(EnrollStudentSubjectTool::class, [
            'enrollment_id' => $enrollment->id,
            'subject_id' => $unrelatedSubject->id,
            'idempotency_key' => 'enroll-unrelated-1',
        ]);

    $deniedProgram->assertHasErrors(['does not belong to the enrollment program']);

    // Reject class with full capacity
    $fullClass = Classes::factory()->create([
        'school_id' => $this->school->id,
        'subject_id' => $subject->id,
        'subject_code' => $subject->code,
        'school_year' => $enrollment->school_year,
        'semester' => $enrollment->semester,
        'maximum_slots' => 1,
    ]);
    ClassEnrollment::factory()->create([
        'class_id' => $fullClass->id,
        'status' => true,
    ]);

    $deniedFull = KoAkademyServer::actingAs($this->staff)
        ->tool(EnrollStudentSubjectTool::class, [
            'enrollment_id' => $enrollment->id,
            'subject_id' => $subject->id,
            'class_id' => $fullClass->id,
            'idempotency_key' => 'enroll-full-1',
        ]);

    $deniedFull->assertHasErrors(['has no available seats']);

    // Setup tuition to verify recalculation
    $tuition = StudentTuition::query()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'academic_year' => 1,
        'total_tuition' => 0.00,
        'total_balance' => 500.00,
        'total_lectures' => 0.00,
        'total_laboratory' => 0.00,
        'total_miscelaneous_fees' => 500.00,
        'overall_tuition' => 500.00,
        'paid' => 0.00,
        'status' => 'Pending',
        'school_year' => $enrollment->school_year,
        'semester' => $enrollment->semester,
    ]);

    $response = KoAkademyServer::actingAs($this->staff)
        ->tool(EnrollStudentSubjectTool::class, [
            'enrollment_id' => $enrollment->id,
            'subject_id' => $subject->id,
            'section' => 'Section-B',
            'idempotency_key' => 'enroll-subj-1',
        ]);

    $response->assertOk()
        ->assertStructuredContent(function ($json) use ($enrollment): void {
            $json->where('enrollment_id', $enrollment->id)
                ->where('subject.code', 'HIST101')
                ->where('section', 'Section-B')
                ->where('replayed', false)
                ->etc();
        });

    expect(SubjectEnrollment::query()->where('enrollment_id', $enrollment->id)->where('subject_id', $subject->id)->count())->toBe(1);
    expect($tuition->refresh()->total_tuition)->toBeGreaterThan(0.00);

    // Replay with identical idempotency key is safe
    $replayed = KoAkademyServer::actingAs($this->staff)
        ->tool(EnrollStudentSubjectTool::class, [
            'enrollment_id' => $enrollment->id,
            'subject_id' => $subject->id,
            'section' => 'Section-B',
            'idempotency_key' => 'enroll-subj-1',
        ]);

    $replayed->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('replayed', true)->etc();
        });

    expect(SubjectEnrollment::query()->where('enrollment_id', $enrollment->id)->where('subject_id', $subject->id)->count())->toBe(1);
});

it('resolves student and subject dynamically and supports batch subject enrollment in EnrollStudentSubjectTool', function (): void {
    $writeToken = $this->staff->createToken('Staff Write', ['mcp:read', 'mcp:write']);
    $this->staff->withAccessToken($writeToken->accessToken);
    $this->staff->givePermissionTo('Update:StudentEnrollment');

    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $course = Course::factory()->create(['school_id' => $this->school->id]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_id' => $this->school->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
    ]);

    $sub1 = Subject::factory()->create(['course_id' => $course->id, 'code' => 'ENG101', 'units' => 3]);
    $sub2 = Subject::factory()->create(['course_id' => $course->id, 'code' => 'MATH101', 'units' => 3]);

    // 1. Single dynamic resolution by student_id and subject_code
    $singleRes = KoAkademyServer::actingAs($this->staff)
        ->tool(EnrollStudentSubjectTool::class, [
            'student_id' => (string) $student->student_id,
            'subject_code' => 'ENG101',
        ]);

    $singleRes->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('subject.code', 'ENG101')
                ->where('replayed', false)
                ->etc();
        });

    expect(SubjectEnrollment::query()->where('enrollment_id', $enrollment->id)->where('subject_id', $sub1->id)->exists())->toBeTrue();

    // 2. Batch enrollment by subjects list
    $batchRes = KoAkademyServer::actingAs($this->staff)
        ->tool(EnrollStudentSubjectTool::class, [
            'student_id' => (string) $student->id,
            'subjects' => [
                ['subject_code' => 'ENG101'],
                ['subject_code' => 'MATH101'],
            ],
        ]);

    $batchRes->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('action', 'batch_enroll')
                ->where('total_enrolled', 2)
                ->where('total_units', 6)
                ->etc();
        });

    expect(SubjectEnrollment::query()->where('enrollment_id', $enrollment->id)->where('subject_id', $sub2->id)->exists())->toBeTrue();

    // 3. Drop dynamically by student_id and subject_code
    $dropRes = KoAkademyServer::actingAs($this->staff)
        ->tool(DropStudentSubjectEnrollmentTool::class, [
            'student_id' => (string) $student->student_id,
            'subject_code' => 'ENG101',
            'reason' => 'Schedule adjustment',
        ]);

    $dropRes->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('dropped', true)
                ->where('subject.code', 'ENG101')
                ->etc();
        });

    expect(SubjectEnrollment::query()->where('enrollment_id', $enrollment->id)->where('subject_id', $sub1->id)->exists())->toBeFalse();
});

it('updates grades and remarks on a subject enrollment with idempotency and evaluates policy outcomes', function (): void {
    $writeToken = $this->staff->createToken('Staff Write', ['mcp:read', 'mcp:write']);
    $this->staff->withAccessToken($writeToken->accessToken);
    $this->staff->givePermissionTo('Update:StudentEnrollment');

    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'school_id' => $this->school->id,
    ]);
    $subject = Subject::factory()->create(['code' => 'CS201', 'units' => 3]);

    $subjectEnrollment = SubjectEnrollment::factory()->create([
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'enrollment_id' => $enrollment->id,
        'class_id' => null,
        'school_id' => $this->school->id,
        'grade' => null,
        'remarks' => null,
    ]);

    // Reject term-grade update when no class_id is linked
    $deniedTerm = KoAkademyServer::actingAs($this->staff)
        ->tool(UpdateSubjectEnrollmentGradeTool::class, [
            'subject_enrollment_id' => $subjectEnrollment->id,
            'prelim_grade' => 85.0,
            'idempotency_key' => 'grade-term-denied-1',
        ]);

    $deniedTerm->assertHasErrors(['Term-grade components (prelim, midterm, finals) require a linked scheduled class enrollment.']);

    $response = KoAkademyServer::actingAs($this->staff)
        ->tool(UpdateSubjectEnrollmentGradeTool::class, [
            'subject_enrollment_id' => $subjectEnrollment->id,
            'grade' => 92.5,
            'remarks' => 'Passed with honors',
            'idempotency_key' => 'grade-update-1',
        ]);

    $response->assertOk()
        ->assertStructuredContent(function ($json) use ($subjectEnrollment): void {
            $json->where('id', $subjectEnrollment->id)
                ->where('grade', 92.5)
                ->where('remarks', 'Passed with honors')
                ->where('replayed', false)
                ->has('grade_outcome')
                ->etc();
        });

    expect($subjectEnrollment->refresh()->grade)->toBe(92.5)
        ->and($subjectEnrollment->remarks)->toBe('Passed with honors')
        ->and($subjectEnrollment->grade_outcome)->not->toBeNull();

    // Replaying returns cached successful response
    $replayed = KoAkademyServer::actingAs($this->staff)
        ->tool(UpdateSubjectEnrollmentGradeTool::class, [
            'subject_enrollment_id' => $subjectEnrollment->id,
            'grade' => 92.5,
            'remarks' => 'Passed with honors',
            'idempotency_key' => 'grade-update-1',
        ]);

    $replayed->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('replayed', true)->etc();
        });
});

it('drops an enrolled subject releasing the record and recalculating tuition with idempotency', function (): void {
    $writeToken = $this->staff->createToken('Staff Write', ['mcp:read', 'mcp:write']);
    $this->staff->withAccessToken($writeToken->accessToken);
    $this->staff->givePermissionTo('Update:StudentEnrollment');

    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $course = Course::factory()->create(['school_id' => $this->school->id, 'lec_per_unit' => 300]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_id' => $this->school->id,
    ]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'code' => 'CHEM101', 'title' => 'General Chemistry', 'lecture' => 3]);

    $subjectEnrollment = SubjectEnrollment::factory()->create([
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'enrollment_id' => $enrollment->id,
        'school_id' => $this->school->id,
    ]);

    $tuition = StudentTuition::query()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'academic_year' => 1,
        'total_tuition' => 900.00,
        'total_balance' => 900.00,
        'total_lectures' => 900.00,
        'total_laboratory' => 0.00,
        'total_miscelaneous_fees' => 0.00,
        'overall_tuition' => 900.00,
        'paid' => 0.00,
        'status' => 'Pending',
        'school_year' => $enrollment->school_year,
        'semester' => $enrollment->semester,
    ]);

    $id = $subjectEnrollment->id;

    $response = KoAkademyServer::actingAs($this->staff)
        ->tool(DropStudentSubjectEnrollmentTool::class, [
            'subject_enrollment_id' => $id,
            'reason' => 'Schedule conflict with job training',
            'idempotency_key' => 'drop-chem-1',
        ]);

    $response->assertOk()
        ->assertStructuredContent(function ($json) use ($id): void {
            $json->where('subject_enrollment_id', $id)
                ->where('dropped', true)
                ->where('subject.code', 'CHEM101')
                ->where('replayed', false)
                ->etc();
        });

    expect(SubjectEnrollment::query()->find($id))->toBeNull();
    expect($tuition->refresh()->total_tuition)->toBe(0.00);

    // Replaying drop with same idempotency key is safely idempotent
    $replayed = KoAkademyServer::actingAs($this->staff)
        ->tool(DropStudentSubjectEnrollmentTool::class, [
            'subject_enrollment_id' => $id,
            'reason' => 'Schedule conflict with job training',
            'idempotency_key' => 'drop-chem-1',
        ]);

    $replayed->assertOk()
        ->assertStructuredContent(function ($json) use ($id): void {
            $json->where('subject_enrollment_id', $id)
                ->where('dropped', true)
                ->where('replayed', true)
                ->etc();
        });
});

it('queries timetable schedule across rooms, students, and faculty via MCP', function (): void {
    $this->staff->createToken('Staff Agent', ['mcp:read']);
    $this->staff->givePermissionTo('ViewAny:ClassSchedule');

    $room = App\Models\Room::create(['name' => 'Room 402', 'is_active' => true]);
    $faculty = Faculty::factory()->create([
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
        'faculty_id_number' => 'FAC-402',
    ]);
    $class = Classes::factory()->create([
        'subject_code' => 'CS402',
        'section' => 'BSCS-4A',
        'school_year' => '2026-2027',
        'semester' => 1,
        'room_id' => $room->id,
        'faculty_id' => $faculty->id,
    ]);
    App\Models\Schedule::create([
        'class_id' => $class->id,
        'day_of_week' => 'Monday',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'room_id' => $room->id,
    ]);

    // Query room schedule
    $response = KoAkademyServer::actingAs($this->staff)
        ->tool(App\Mcp\Tools\QueryTimetableScheduleTool::class, [
            'target_type' => 'room',
            'identifier' => 'Room 402',
            'check_availability' => true,
        ]);

    $response->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('type', 'room')
                ->where('rooms.0.name', 'Room 402')
                ->where('rooms.0.schedules.0.subject_code', 'CS402')
                ->where('rooms.0.schedules.0.day_of_week', 'Monday')
                ->etc();
        });

    // Query faculty schedule
    $facultyResponse = KoAkademyServer::actingAs($this->staff)
        ->tool(App\Mcp\Tools\QueryTimetableScheduleTool::class, [
            'target_type' => 'faculty',
            'identifier' => 'Hopper',
        ]);

    $facultyResponse->assertOk()
        ->assertStructuredContent(function ($json) use ($faculty): void {
            $json->where('type', 'faculty')
                ->where('faculty.0.name', $faculty->full_name)
                ->where('faculty.0.schedules.0.subject_code', 'CS402')
                ->etc();
        });
});

it('performs student CRUD via MCP ManageStudentTool', function (): void {
    config(['api.mcp.write_enabled' => true]);
    $this->staff->createToken('Staff Agent', ['mcp:read', 'mcp:write']);
    $this->staff->givePermissionTo(['View:Student', 'Update:Student', 'Create:Student']);

    $course = Course::factory()->create(['code' => 'BSSE', 'title' => 'Software Engineering']);

    // Create student
    $createResponse = KoAkademyServer::actingAs($this->staff)
        ->tool(App\Mcp\Tools\ManageStudentTool::class, [
            'action' => 'create',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada.lovelace@example.com',
            'course_code' => 'BSSE',
            'academic_year' => 1,
            'student_type' => 'college',
            'idempotency_key' => 'create-ada-1',
        ]);

    $createResponse->assertOk();
    $student = Student::query()->where('email', 'ada.lovelace@example.com')->firstOrFail();

    $createResponse->assertStructuredContent(function ($json) use ($student): void {
        $json->where('success', true)
            ->where('action', 'create')
            ->where('student.name', $student->full_name)
            ->where('student.email', 'ada.lovelace@example.com')
            ->where('replayed', false)
            ->etc();
    });

    // Replay student creation with same idempotency key
    $replayResponse = KoAkademyServer::actingAs($this->staff)
        ->tool(App\Mcp\Tools\ManageStudentTool::class, [
            'action' => 'create',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada.lovelace@example.com',
            'course_code' => 'BSSE',
            'academic_year' => 1,
            'student_type' => 'college',
            'idempotency_key' => 'create-ada-1',
        ]);

    $replayResponse->assertOk()
        ->assertStructuredContent(function ($json) use ($student): void {
            $json->where('success', true)
                ->where('action', 'create')
                ->where('student.id', $student->id)
                ->where('replayed', true)
                ->etc();
        });

    // Update student
    $updateResponse = KoAkademyServer::actingAs($this->staff)
        ->tool(App\Mcp\Tools\ManageStudentTool::class, [
            'action' => 'update',
            'student_id' => $student->id,
            'academic_year' => 2,
            'status' => 'enrolled',
        ]);

    $updateResponse->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('success', true)
                ->where('action', 'update')
                ->where('student.status', 'enrolled')
                ->etc();
        });

    expect($student->refresh()->academic_year)->toBe(2);
});

it('performs curriculum subject and class schedule management via MCP', function (): void {
    config(['api.mcp.write_enabled' => true]);
    $this->staff->createToken('Staff Agent', ['mcp:read', 'mcp:write']);
    $this->staff->givePermissionTo(['View:Subject', 'Update:Subject', 'View:Classes', 'Update:Classes', 'View:Room', 'Update:Room']);

    $course = Course::factory()->create(['code' => 'BSDS', 'title' => 'Data Science']);

    // Manage room
    $roomResponse = KoAkademyServer::actingAs($this->staff)
        ->tool(App\Mcp\Tools\ManageRoomTool::class, [
            'action' => 'create',
            'name' => 'Analytics Lab 1',
        ]);
    $roomResponse->assertOk();
    $room = App\Models\Room::query()->where('name', 'Analytics Lab 1')->firstOrFail();

    // Create subject
    $subjectResponse = KoAkademyServer::actingAs($this->staff)
        ->tool(App\Mcp\Tools\ManageCurriculumSubjectTool::class, [
            'action' => 'create',
            'code' => 'DS101',
            'title' => 'Introduction to Data Science',
            'units' => 3,
            'academic_year' => 1,
            'semester' => 1,
            'course_code' => 'BSDS',
        ]);
    $subjectResponse->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('success', true)
                ->where('subject.code', 'DS101')
                ->etc();
        });

    // Create class schedule
    $classResponse = KoAkademyServer::actingAs($this->staff)
        ->tool(App\Mcp\Tools\ManageClassScheduleTool::class, [
            'action' => 'create_class',
            'subject_code' => 'DS101',
            'section' => 'BSDS-1A',
            'room_id' => $room->id,
            'day_of_week' => 'Wednesday',
            'start_time' => '13:00',
            'end_time' => '15:00',
        ]);

    $classResponse->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('success', true)
                ->where('action', 'create_class')
                ->where('class.subject_code', 'DS101')
                ->etc();
        });
});
