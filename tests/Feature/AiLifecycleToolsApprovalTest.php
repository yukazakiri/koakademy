<?php

declare(strict_types=1);

use App\Ai\Mcp\ResilientMcpServerTool;
use App\Ai\Tools\InvestigateStudentEnrollmentTool;
use App\Ai\Tools\InvestigateStudentFinancesTool;
use App\Ai\Tools\ManageEnrollmentTool as AiManageEnrollmentTool;
use App\Ai\Tools\ManageStudentTool as AiManageStudentTool;
use App\Ai\Tools\ManageSubjectEnrollmentTool as AiManageSubjectEnrollmentTool;
use App\Enums\UserRole;
use App\Mcp\Tools\ManageEnrollmentTool as McpManageEnrollmentTool;
use App\Mcp\Tools\ManageSubjectEnrollmentTool as McpManageSubjectEnrollmentTool;
use App\Models\Course;
use App\Models\Department;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\TenantContext;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Tools\Request as AiRequest;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    config(['activitylog.enabled' => false]);

    $this->school = School::factory()->create();

    $this->settings = GeneralSetting::create([
        'school_starting_date' => now()->startOfYear(),
        'school_ending_date' => now()->startOfYear()->addYear(),
        'semester' => 1,
        'site_name' => 'KoAkademy AI Test School',
    ]);

    $this->admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => $this->school->id,
    ]);

    app(TenantContext::class)->setCurrentSchool($this->school);

    $permissions = [
        'ViewAny:Student',
        'View:Student',
        'Create:Student',
        'Update:Student',
        'Delete:Student',
        'Restore:Student',
        'ViewAny:StudentEnrollment',
        'View:StudentEnrollment',
        'Create:StudentEnrollment',
        'Update:StudentEnrollment',
        'Delete:StudentEnrollment',
        'Restore:StudentEnrollment',
        'view_tuition_fees',
        'View:Cashier',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $this->admin->assignRole('super_admin');
});

it('requires user approval for destructive and transferring actions in AI ManageStudentTool', function (): void {
    $tool = new AiManageStudentTool;

    $reflector = new ReflectionClass($tool);
    $needsApproval = $reflector->getMethod('needsApproval');
    $needsApproval->setAccessible(true);

    // Soft delete requires approval
    $approvalDelete = $needsApproval->invoke($tool, new AiRequest([
        'action' => 'soft_delete',
        'student_id' => '101',
    ]));
    expect($approvalDelete)->toBeInstanceOf(Approval::class)
        ->and($approvalDelete->reason)->toContain('Soft-delete student #101');

    // Restore requires approval
    $approvalRestore = $needsApproval->invoke($tool, new AiRequest([
        'action' => 'restore',
        'student_id' => '101',
    ]));
    expect($approvalRestore)->toBeInstanceOf(Approval::class)
        ->and($approvalRestore->reason)->toContain('Restore soft-deleted student record #101');

    // Transfer records requires approval (when preview is false)
    $approvalTransfer = $needsApproval->invoke($tool, new AiRequest([
        'action' => 'transfer_records',
        'student_id' => '101',
        'target_student_id' => '202',
    ]));
    expect($approvalTransfer)->toBeInstanceOf(Approval::class)
        ->and($approvalTransfer->reason)->toContain('Permanently transfer');

    // Transfer preview does NOT require approval
    $previewCheck = $needsApproval->invoke($tool, new AiRequest([
        'action' => 'transfer_records',
        'student_id' => '101',
        'target_student_id' => '202',
        'preview' => true,
    ]));
    expect($previewCheck)->toBeFalse();

    // Get does NOT require approval
    $getCheck = $needsApproval->invoke($tool, new AiRequest([
        'action' => 'get',
        'student_id' => '101',
    ]));
    expect($getCheck)->toBeFalse();
});

it('requires user approval for mutations in AI ManageEnrollmentTool', function (): void {
    $tool = new AiManageEnrollmentTool;

    $reflector = new ReflectionClass($tool);
    $needsApproval = $reflector->getMethod('needsApproval');
    $needsApproval->setAccessible(true);

    // Create requires approval
    $createApproval = $needsApproval->invoke($tool, new AiRequest([
        'action' => 'create',
        'student_id' => '101',
    ]));
    expect($createApproval)->toBeInstanceOf(Approval::class)
        ->and($createApproval->reason)->toContain('Create new official enrollment record');

    // Soft delete requires approval
    $deleteApproval = $needsApproval->invoke($tool, new AiRequest([
        'action' => 'soft_delete',
        'enrollment_id' => 55,
    ]));
    expect($deleteApproval)->toBeInstanceOf(Approval::class)
        ->and($deleteApproval->reason)->toContain('Soft-delete enrollment record #55');

    // Restore requires approval
    $restoreApproval = $needsApproval->invoke($tool, new AiRequest([
        'action' => 'restore',
        'enrollment_id' => 55,
    ]));
    expect($restoreApproval)->toBeInstanceOf(Approval::class)
        ->and($restoreApproval->reason)->toContain('Restore soft-deleted enrollment record #55');

    // Transfer requires approval
    $transferApproval = $needsApproval->invoke($tool, new AiRequest([
        'action' => 'transfer_to_student',
        'enrollment_id' => 55,
        'target_student_id' => '202',
    ]));
    expect($transferApproval)->toBeInstanceOf(Approval::class)
        ->and($transferApproval->reason)->toContain('Transfer enrollment #55 to student 202');
});

it('requires user approval for mutations in AI ManageSubjectEnrollmentTool', function (): void {
    $tool = new AiManageSubjectEnrollmentTool;

    $reflector = new ReflectionClass($tool);
    $needsApproval = $reflector->getMethod('needsApproval');
    $needsApproval->setAccessible(true);

    $enrollApproval = $needsApproval->invoke($tool, new AiRequest([
        'action' => 'enroll',
        'student_id' => '101',
        'subject_code' => 'CS101',
    ]));
    expect($enrollApproval)->toBeInstanceOf(Approval::class)
        ->and($enrollApproval->reason)->toContain('Enroll student 101 into subject CS101');

    $dropApproval = $needsApproval->invoke($tool, new AiRequest([
        'action' => 'drop',
        'subject_enrollment_id' => 42,
    ]));
    expect($dropApproval)->toBeInstanceOf(Approval::class)
        ->and($dropApproval->reason)->toContain('Drop subject enrollment #42');

    $transferApproval = $needsApproval->invoke($tool, new AiRequest([
        'action' => 'transfer_to_student',
        'subject_enrollment_id' => 42,
        'target_student_id' => '202',
    ]));
    expect($transferApproval)->toBeInstanceOf(Approval::class)
        ->and($transferApproval->reason)->toContain('Transfer subject enrollment #42 to student 202');
});

it('enforces approvals in ResilientMcpServerTool for wrapped MCP tools', function (): void {
    $wrappedEnrollment = new ResilientMcpServerTool(new McpManageEnrollmentTool);
    $wrappedSubject = new ResilientMcpServerTool(new McpManageSubjectEnrollmentTool);

    $reflector = new ReflectionClass(ResilientMcpServerTool::class);
    $needsApproval = $reflector->getMethod('needsApproval');
    $needsApproval->setAccessible(true);

    $enrollmentApproval = $needsApproval->invoke($wrappedEnrollment, new AiRequest([]));
    expect($enrollmentApproval)->toBeInstanceOf(Approval::class)
        ->and($enrollmentApproval->reason)->toContain('modify official registration');

    $subjectApproval = $needsApproval->invoke($wrappedSubject, new AiRequest([]));
    expect($subjectApproval)->toBeInstanceOf(Approval::class)
        ->and($subjectApproval->reason)->toContain('modifies official coursework');
});

it('executes AI investigation tools returning structured findings', function (): void {
    $dept = Department::factory()->create();
    $course = Course::factory()->create(['department_id' => $dept->id]);

    $student = Student::factory()->create([
        'school_id' => $this->school->id,
        'institution_id' => $this->school->id,
        'course_id' => $course->id,
    ]);

    $this->actingAs($this->admin);

    $enrollmentTool = new InvestigateStudentEnrollmentTool;
    $enrollmentResult = json_decode((string) $enrollmentTool->handle(new AiRequest([
        'student_id' => (string) $student->id,
    ])), true);

    expect($enrollmentResult['investigation_type'])->toBe('enrollment_records_audit')
        ->and($enrollmentResult['status'])->toBe('clean');

    $financeTool = new InvestigateStudentFinancesTool;
    $financeResult = json_decode((string) $financeTool->handle(new AiRequest([
        'student_id' => (string) $student->id,
    ])), true);

    expect($financeResult['investigation_type'])->toBe('financial_ledger_audit')
        ->and($financeResult['status'])->toBe('clean');
});
