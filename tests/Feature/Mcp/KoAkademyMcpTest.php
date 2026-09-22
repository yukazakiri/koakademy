<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Mcp\Servers\KoAkademyServer;
use App\Mcp\Tools\AdvanceEnrollmentStepTool;
use App\Mcp\Tools\GetEnrollmentAuditTrailTool;
use App\Mcp\Tools\GetEnrollmentStatusTool;
use App\Mcp\Tools\GetMyContextTool;
use App\Mcp\Tools\GetSchoolDetailsTool;
use App\Mcp\Tools\GetSchoolMetricsTool;
use App\Mcp\Tools\GetStatementOfAccountTool;
use App\Mcp\Tools\GetStudentScheduleTool;
use App\Mcp\Tools\ListAcademicOfferingsTool;
use App\Mcp\Tools\ListPendingEnrollmentsTool;
use App\Mcp\Tools\SearchFacultyTool;
use App\Mcp\Tools\SearchStudentsTool;
use App\Mcp\Tools\UpdateEnrollmentRemarksTool;
use App\Mcp\Tools\VerifyEnrollmentRequirementTool;
use App\Models\Course;
use App\Models\Department;
use App\Models\EnrollmentRequirement;
use App\Models\Faculty;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
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
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
});

it('registers all fourteen core mcp tools on the server', function (): void {
    KoAkademyServer::tools()
        ->assertRegistered([
            GetMyContextTool::class,
            GetSchoolDetailsTool::class,
            GetSchoolMetricsTool::class,
            SearchStudentsTool::class,
            GetStudentScheduleTool::class,
            SearchFacultyTool::class,
            GetEnrollmentStatusTool::class,
            ListPendingEnrollmentsTool::class,
            GetEnrollmentAuditTrailTool::class,
            ListAcademicOfferingsTool::class,
            GetStatementOfAccountTool::class,
            AdvanceEnrollmentStepTool::class,
            VerifyEnrollmentRequirementTool::class,
            UpdateEnrollmentRemarksTool::class,
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
