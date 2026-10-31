<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\StudentOrganizationAssignmentService;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * SEC-008 · Faculty password, student user_id and school_id are writable through the public API.
 */
beforeEach(function (): void {
    config(['activitylog.enabled' => false]);

    GeneralSetting::create([
        'school_starting_date' => now()->startOfYear(),
        'school_ending_date' => now()->startOfYear()->addYear(),
        'semester' => 1,
        'site_name' => 'Test School',
    ]);

    $permissions = [
        'ViewAny:Student', 'View:Student', 'Create:Student', 'Update:Student', 'Delete:Student',
        'ViewAny:Faculty', 'View:Faculty', 'Create:Faculty', 'Update:Faculty', 'Delete:Faculty',
        'ViewAny:Course', 'View:Course', 'Create:Course', 'Update:Course', 'Delete:Course',
        'ViewAny:StudentEnrollment', 'View:StudentEnrollment', 'Create:StudentEnrollment', 'Update:StudentEnrollment', 'Delete:StudentEnrollment',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $this->school = School::factory()->create();
});

it('never updates faculty password through the public faculty API endpoint', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => $this->school->id,
    ]);
    $admin->givePermissionTo('Update:Faculty');

    $originalPasswordHash = Hash::make('original-password');
    $faculty = Faculty::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Original',
        'last_name' => 'Professor',
        'password' => $originalPasswordHash,
    ]);

    Sanctum::actingAs($admin);

    $response = $this->putJson("/api/faculties/{$faculty->id}", [
        'first_name' => 'Renamed',
        'password' => 'attacker-new-password-12345',
    ]);

    $response->assertSuccessful();

    $faculty->refresh();
    expect($faculty->first_name)->toBe('Renamed')
        ->and(Hash::check('attacker-new-password-12345', $faculty->password))->toBeFalse()
        ->and(Hash::check('original-password', $faculty->password))->toBeTrue();
});

it('never allows a faculty member to update their password through the faculty API update endpoint', function (): void {
    $originalPasswordHash = Hash::make('faculty-original-pw');
    $facultyUser = User::factory()->create([
        'role' => UserRole::Instructor,
        'email' => 'self.faculty@example.com',
        'school_id' => $this->school->id,
    ]);
    $facultyUser->givePermissionTo('Update:Faculty');

    $faculty = Faculty::factory()->create([
        'school_id' => $this->school->id,
        'email' => 'self.faculty@example.com',
        'first_name' => 'Self',
        'last_name' => 'Faculty',
        'password' => $originalPasswordHash,
    ]);

    Sanctum::actingAs($facultyUser);

    $response = $this->putJson("/api/faculties/{$faculty->id}", [
        'first_name' => 'UpdatedSelf',
        'password' => 'hijacked-new-password',
    ]);

    $response->assertSuccessful();

    $faculty->refresh();
    expect($faculty->first_name)->toBe('UpdatedSelf')
        ->and(Hash::check('hijacked-new-password', $faculty->password))->toBeFalse()
        ->and(Hash::check('faculty-original-pw', $faculty->password))->toBeTrue();
});

it('never overwrites student user_id through the public student API endpoint', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => $this->school->id,
    ]);
    $admin->givePermissionTo('Update:Student');

    $legitimateUser = User::factory()->create([
        'role' => UserRole::Student,
        'school_id' => $this->school->id,
    ]);
    $attackerUser = User::factory()->create([
        'role' => UserRole::Student,
        'school_id' => $this->school->id,
    ]);

    $student = Student::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $legitimateUser->id,
        'first_name' => 'Original',
        'last_name' => 'Student',
        'document_location_id' => null,
    ]);

    Sanctum::actingAs($admin);

    $response = $this->putJson("/api/students/{$student->id}", [
        'first_name' => 'UpdatedName',
        'user_id' => $attackerUser->id,
    ]);

    $response->assertSuccessful();

    $student->refresh();
    expect($student->first_name)->toBe('UpdatedName')
        ->and($student->user_id)->toBe($legitimateUser->id)
        ->and($student->user_id)->not->toBe($attackerUser->id);
});

it('links student accounts exclusively through the assignment service', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Student,
        'school_id' => null,
    ]);

    $student = Student::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => null,
        'document_location_id' => null,
    ]);

    $assignmentService = app(StudentOrganizationAssignmentService::class);
    $assignmentService->assign($user, $student);

    $student->refresh();
    $user->refresh();

    expect($student->user_id)->toBe($user->id)
        ->and($user->school_id)->toBe($this->school->id);
});

it('refuses student updates from any faculty rank regardless of permission', function (UserRole $role): void {
    $facultyUser = User::factory()->create([
        'role' => $role,
        'school_id' => $this->school->id,
    ]);
    $facultyUser->givePermissionTo('Update:Student');

    $student = Student::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Protected',
        'last_name' => 'Student',
        'document_location_id' => null,
    ]);

    Sanctum::actingAs($facultyUser);

    $response = $this->putJson("/api/students/{$student->id}", [
        'first_name' => 'AttackerModified',
    ]);

    $response->assertForbidden();

    $student->refresh();
    expect($student->first_name)->toBe('Protected');
})->with([
    'Instructor' => UserRole::Instructor,
    'Assistant Professor' => UserRole::AssistantProfessor,
    'Associate Professor' => UserRole::AssociateProfessor,
    'Professor' => UserRole::Professor,
    'Part-time Faculty' => UserRole::PartTimeFaculty,
    'Dean' => UserRole::Dean,
]);

it('does not allow overriding school_id or id when creating or updating a course', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => $this->school->id,
    ]);
    $admin->givePermissionTo(['Create:Course', 'Update:Course']);

    $department = Department::factory()->create(['school_id' => $this->school->id]);

    Sanctum::actingAs($admin);

    $otherSchool = School::factory()->create();

    $createResponse = $this->postJson('/api/courses', [
        'code' => 'CS999',
        'title' => 'Computer Security',
        'description' => 'Security fundamentals',
        'department_id' => $department->id,
        'lec_per_unit' => '100',
        'lab_per_unit' => '200',
        'remarks' => 'None',
        'curriculum_year' => '2026',
        'miscelaneous' => '500',
        'units' => '3',
        'year_level' => '1',
        'semester' => '1',
        'school_year' => '2026 - 2027',
        'miscellaneous' => '500',
        'is_active' => true,
        'school_id' => $otherSchool->id,
        'id' => 99999,
    ]);

    $createResponse->assertSuccessful();

    $course = Course::query()->where('code', 'CS999')->firstOrFail();
    expect($course->id)->not->toBe(99999)
        ->and($course->school_id)->toBe($this->school->id);

    $updateResponse = $this->putJson("/api/courses/{$course->id}", [
        'code' => 'CS999-EDIT',
        'title' => 'Computer Security Advanced',
        'description' => 'Security fundamentals updated',
        'department_id' => $department->id,
        'lec_per_unit' => '100',
        'lab_per_unit' => '200',
        'remarks' => 'Updated remarks',
        'curriculum_year' => '2026',
        'miscelaneous' => '500',
        'units' => '3',
        'year_level' => '1',
        'semester' => '1',
        'school_year' => '2026 - 2027',
        'miscellaneous' => '500',
        'is_active' => true,
        'school_id' => $otherSchool->id,
        'id' => 99999,
    ]);

    $updateResponse->assertSuccessful();

    $course->refresh();
    expect($course->code)->toBe('CS999-EDIT')
        ->and($course->id)->not->toBe(99999)
        ->and($course->school_id)->toBe($this->school->id);
});

it('does not allow overriding school_id, student_id or id when updating student enrollment', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => $this->school->id,
    ]);
    $admin->givePermissionTo('Update:StudentEnrollment');

    $course = Course::factory()->create(['school_id' => $this->school->id]);
    $student = Student::factory()->create(['school_id' => $this->school->id, 'course_id' => $course->id]);
    $otherStudent = Student::factory()->create(['school_id' => $this->school->id, 'course_id' => $course->id]);
    $otherSchool = School::factory()->create();

    $enrollment = StudentEnrollment::factory()->legacyWorkflow()->create([
        'school_id' => $this->school->id,
        'student_id' => $student->id,
        'course_id' => $course->id,
        'semester' => 1,
        'academic_year' => 1,
        'school_year' => '2026 - 2027',
        'remarks' => 'Original remarks',
    ]);

    Sanctum::actingAs($admin);

    $response = $this->putJson("/api/student-enrollments/{$enrollment->id}", [
        'remarks' => 'Updated safe remarks',
        'school_id' => $otherSchool->id,
        'student_id' => $otherStudent->id,
        'id' => 99999,
    ]);

    $response->assertSuccessful();

    $enrollment->refresh();
    expect($enrollment->remarks)->toBe('Updated safe remarks')
        ->and($enrollment->school_id)->toBe($this->school->id)
        ->and($enrollment->student_id)->toBe($student->id)
        ->and($enrollment->id)->not->toBe(99999);
});
