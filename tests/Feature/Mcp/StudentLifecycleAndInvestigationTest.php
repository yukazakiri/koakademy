<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Mcp\Servers\KoAkademyServer;
use App\Mcp\Tools\InvestigateStudentEnrollmentTool;
use App\Mcp\Tools\InvestigateStudentFinancesTool;
use App\Mcp\Tools\ManageEnrollmentTool;
use App\Mcp\Tools\ManageStudentTool;
use App\Mcp\Tools\ManageSubjectEnrollmentTool;
use App\Models\Course;
use App\Models\Department;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\User;
use App\Services\TenantContext;
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
        'site_name' => 'KoAkademy MCP Test School',
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
        'View:Cashier',
        'view_tuition_fees',
        'ViewAny:Classes',
        'View:Classes',
        'Update:Classes',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $this->token = $this->admin->createToken('Admin Agent', ['mcp:read', 'mcp:write']);
});

it('soft deletes and restores student record via ManageStudentTool', function (): void {
    $student = Student::factory()->create([
        'school_id' => $this->school->id,
        'institution_id' => $this->school->id,
        'first_name' => 'Arthur',
        'last_name' => 'Dent',
    ]);

    // Soft delete
    $deleteResponse = KoAkademyServer::actingAs($this->admin)
        ->tool(ManageStudentTool::class, [
            'action' => 'soft_delete',
            'student_id' => (string) $student->id,
            'reason' => 'Duplicate record created accidentally',
        ]);

    $deleteResponse->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('success', true)
                ->where('action', 'soft_delete')
                ->etc();
        });

    expect($student->fresh()->trashed())->toBeTrue();

    // Inspect with trashed
    $getResponse = KoAkademyServer::actingAs($this->admin)
        ->tool(ManageStudentTool::class, [
            'action' => 'get',
            'student_id' => (string) $student->id,
            'include_trashed' => true,
        ]);

    $getResponse->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('found', true)
                ->where('is_trashed', true)
                ->etc();
        });

    // Restore
    $restoreResponse = KoAkademyServer::actingAs($this->admin)
        ->tool(ManageStudentTool::class, [
            'action' => 'restore',
            'student_id' => (string) $student->id,
        ]);

    $restoreResponse->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('success', true)
                ->where('action', 'restore')
                ->etc();
        });

    expect($student->fresh()->trashed())->toBeFalse();
});

it('previews and transfers records from one student to another', function (): void {
    $dept = Department::factory()->create();
    $course = Course::factory()->create(['department_id' => $dept->id]);

    $studentA = Student::factory()->create([
        'school_id' => $this->school->id,
        'institution_id' => $this->school->id,
        'course_id' => $course->id,
    ]);

    $studentB = Student::factory()->create([
        'school_id' => $this->school->id,
        'institution_id' => $this->school->id,
        'course_id' => $course->id,
    ]);

    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => (string) $studentA->id,
        'school_id' => $this->school->id,
        'course_id' => $course->id,
    ]);

    $subject = Subject::factory()->create(['course_id' => $course->id]);
    SubjectEnrollment::factory()->create([
        'student_id' => $studentA->id,
        'enrollment_id' => $enrollment->id,
        'subject_id' => $subject->id,
        'school_id' => $this->school->id,
    ]);

    // Preview
    $previewResponse = KoAkademyServer::actingAs($this->admin)
        ->tool(ManageStudentTool::class, [
            'action' => 'transfer_records',
            'student_id' => (string) $studentA->id,
            'target_student_id' => (string) $studentB->id,
            'preview' => true,
        ]);

    $previewResponse->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('action', 'transfer_records_preview')
                ->where('records_to_transfer.enrollments_count', 1)
                ->where('records_to_transfer.subject_enrollments_count', 1)
                ->etc();
        });

    // Execute transfer with confirm=true
    $transferResponse = KoAkademyServer::actingAs($this->admin)
        ->tool(ManageStudentTool::class, [
            'action' => 'transfer_records',
            'student_id' => (string) $studentA->id,
            'target_student_id' => (string) $studentB->id,
            'confirm' => true,
            'reason' => 'Consolidating duplicated identity profiles',
        ]);

    $transferResponse->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('success', true)
                ->where('transferred.enrollments', 1)
                ->where('transferred.subject_enrollments', 1)
                ->etc();
        });

    expect($enrollment->fresh()->student_id)->toBe((string) $studentB->id);
});

it('manages enrollment lifecycle including soft delete and restore via ManageEnrollmentTool', function (): void {
    $dept = Department::factory()->create();
    $course = Course::factory()->create(['department_id' => $dept->id, 'code' => 'BSCS']);

    $student = Student::factory()->create([
        'school_id' => $this->school->id,
        'institution_id' => $this->school->id,
        'course_id' => $course->id,
    ]);

    // Create enrollment
    $createResponse = KoAkademyServer::actingAs($this->admin)
        ->tool(ManageEnrollmentTool::class, [
            'action' => 'create',
            'student_id' => (string) $student->id,
            'course_code' => 'BSCS',
            'school_year' => '2025 - 2026',
            'semester' => 1,
            'status' => 'enrolled',
            'downpayment' => 3000,
        ]);

    $createResponse->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('success', true)
                ->where('action', 'create')
                ->where('enrollment.school_year', '2025 - 2026')
                ->etc();
        });

    $enrollment = StudentEnrollment::query()->where('student_id', (string) $student->id)->first();
    expect($enrollment)->not->toBeNull();
    $enrollmentId = $enrollment->id;

    // Update status
    $statusResponse = KoAkademyServer::actingAs($this->admin)
        ->tool(ManageEnrollmentTool::class, [
            'action' => 'update_status',
            'enrollment_id' => $enrollmentId,
            'status' => 'cancelled',
            'reason' => 'Student opted for leave of absence',
        ]);

    $statusResponse->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('success', true)
                ->where('new_status', 'cancelled')
                ->etc();
        });

    // Soft delete enrollment
    $deleteResponse = KoAkademyServer::actingAs($this->admin)
        ->tool(ManageEnrollmentTool::class, [
            'action' => 'soft_delete',
            'enrollment_id' => $enrollmentId,
            'reason' => 'Testing deletion',
        ]);

    $deleteResponse->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('success', true)
                ->where('action', 'soft_delete')
                ->etc();
        });

    $enrollmentModel = StudentEnrollment::withTrashed()->find($enrollmentId);
    expect($enrollmentModel->trashed())->toBeTrue();

    // Restore enrollment
    $restoreResponse = KoAkademyServer::actingAs($this->admin)
        ->tool(ManageEnrollmentTool::class, [
            'action' => 'restore',
            'enrollment_id' => $enrollmentId,
        ]);

    $restoreResponse->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('success', true)
                ->where('action', 'restore')
                ->etc();
        });

    expect($enrollmentModel->fresh()->trashed())->toBeFalse();
});

it('manages subject enrollments including grades and details via ManageSubjectEnrollmentTool', function (): void {
    $dept = Department::factory()->create();
    $course = Course::factory()->create(['department_id' => $dept->id]);

    $student = Student::factory()->create([
        'school_id' => $this->school->id,
        'institution_id' => $this->school->id,
        'course_id' => $course->id,
    ]);

    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => (string) $student->id,
        'school_id' => $this->school->id,
        'course_id' => $course->id,
    ]);

    $subject = Subject::factory()->create(['course_id' => $course->id, 'code' => 'CS101']);

    // Enroll in subject
    $enrollResponse = KoAkademyServer::actingAs($this->admin)
        ->tool(ManageSubjectEnrollmentTool::class, [
            'action' => 'enroll',
            'student_id' => (string) $student->id,
            'enrollment_id' => $enrollment->id,
            'subject_code' => 'CS101',
            'is_modular' => false,
            'lecture_fee' => 1500,
        ]);

    $enrollResponse->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('success', true)
                ->where('action', 'enroll')
                ->where('subject_enrollment.subject_code', 'CS101')
                ->etc();
        });

    $subjectEnrollment = SubjectEnrollment::query()->where('student_id', $student->id)->first();
    expect($subjectEnrollment)->not->toBeNull();
    $subjectEnrollmentId = $subjectEnrollment->id;

    // Update grade
    $gradeResponse = KoAkademyServer::actingAs($this->admin)
        ->tool(ManageSubjectEnrollmentTool::class, [
            'action' => 'update_grade',
            'subject_enrollment_id' => $subjectEnrollmentId,
            'grade' => 1.5,
            'grade_symbol' => '1.50',
            'remarks' => 'Passed',
        ]);

    $gradeResponse->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('success', true)
                ->where('subject_enrollment.grade', 1.5)
                ->where('subject_enrollment.remarks', 'Passed')
                ->etc();
        });

    // Drop subject
    $dropResponse = KoAkademyServer::actingAs($this->admin)
        ->tool(ManageSubjectEnrollmentTool::class, [
            'action' => 'drop',
            'subject_enrollment_id' => $subjectEnrollmentId,
            'reason' => 'Schedule adjustment',
        ]);

    $dropResponse->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('success', true)
                ->where('action', 'drop')
                ->etc();
        });

    expect(SubjectEnrollment::query()->find($subjectEnrollmentId))->toBeNull();
});

it('investigates enrollment records detecting prerequisite violations and duplicate subjects', function (): void {
    $dept = Department::factory()->create();
    $course = Course::factory()->create(['department_id' => $dept->id]);

    $student = Student::factory()->create([
        'school_id' => $this->school->id,
        'institution_id' => $this->school->id,
        'course_id' => $course->id,
    ]);

    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => (string) $student->id,
        'school_id' => $this->school->id,
        'course_id' => $course->id,
    ]);

    // Subject with prerequisite CS101 that the student has NOT passed
    $advancedSubject = Subject::factory()->create([
        'course_id' => $course->id,
        'code' => 'CS102',
        'pre_riquisite' => 'CS101',
    ]);

    // Enroll in CS102
    SubjectEnrollment::factory()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'subject_id' => $advancedSubject->id,
        'school_id' => $this->school->id,
    ]);

    // Also add duplicate enrollment for CS102
    SubjectEnrollment::factory()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'subject_id' => $advancedSubject->id,
        'school_id' => $this->school->id,
    ]);

    $investigationResponse = KoAkademyServer::actingAs($this->admin)
        ->tool(InvestigateStudentEnrollmentTool::class, [
            'student_id' => (string) $student->id,
        ]);

    $investigationResponse->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('investigation_type', 'enrollment_records_audit')
                ->where('status', 'critical_attention_required')
                ->where('critical_issues', 3) // 2 prereq violations (both instances) + 1 duplicate enrollment
                ->etc();
        });
});

it('investigates finances detecting balance desynchronization and status mismatch', function (): void {
    $dept = Department::factory()->create();
    $course = Course::factory()->create(['department_id' => $dept->id]);

    $student = Student::factory()->create([
        'school_id' => $this->school->id,
        'institution_id' => $this->school->id,
        'course_id' => $course->id,
    ]);

    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => (string) $student->id,
        'school_id' => $this->school->id,
        'course_id' => $course->id,
    ]);

    // Create a corrupted tuition ledger where stored balance does not equal overall_tuition - paid
    StudentTuition::create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'overall_tuition' => 25000,
        'total_balance' => 10000, // Should be 25000 since paid=0!
        'paid' => 0,
        'status' => 'Paid', // Marked 'Paid' when balance is actually due!
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'academic_year' => 1,
    ]);

    $investigationResponse = KoAkademyServer::actingAs($this->admin)
        ->tool(InvestigateStudentFinancesTool::class, [
            'student_id' => (string) $student->id,
        ]);

    $investigationResponse->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('investigation_type', 'financial_ledger_audit')
                ->where('discrepancies_found', 2) // balance desync + status mismatch
                ->where('critical_discrepancies', 1)
                ->etc();
        });
});
