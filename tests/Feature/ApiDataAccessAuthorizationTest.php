<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Faculty;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    config(['activitylog.enabled' => false]);

    // API requests are tenant-scoped; every fixture belongs to this school.
    $this->school = School::factory()->create();

    // Create GeneralSetting record for Student model methods
    GeneralSetting::create([
        'school_starting_date' => now()->startOfYear(),
        'school_ending_date' => now()->startOfYear()->addYear(),
        'semester' => 1,
        'site_name' => 'Test School',
    ]);

    // Create necessary permissions for testing
    $permissions = [
        'ViewAny:Student', 'View:Student', 'Create:Student', 'Update:Student', 'Delete:Student',
        'ViewAny:Faculty', 'View:Faculty', 'Create:Faculty', 'Update:Faculty', 'Delete:Faculty',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
});

describe('API Data Access - Student Portal', function (): void {
    beforeEach(function (): void {
        // Create a student user with associated Student record
        $this->studentUser = User::factory()->create([
            'role' => UserRole::Student,
            'email' => 'student1@example.com',
            'school_id' => $this->school->id,
        ]);

        $this->studentRecord = Student::factory()->create([
            'user_id' => $this->studentUser->id,
            'email' => $this->studentUser->email,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'document_location_id' => null,
            'school_id' => $this->school->id,
        ]);

        // Create another student user with associated Student record
        $this->otherStudentUser = User::factory()->create([
            'role' => UserRole::Student,
            'email' => 'student2@example.com',
            'school_id' => $this->school->id,
        ]);

        $this->otherStudentRecord = Student::factory()->create([
            'user_id' => $this->otherStudentUser->id,
            'email' => $this->otherStudentUser->email,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'document_location_id' => null,
            'school_id' => $this->school->id,
        ]);
    });

    it('can view list of students with read permission', function (): void {
        $this->studentUser->givePermissionTo('ViewAny:Student');

        Sanctum::actingAs($this->studentUser);

        $response = $this->getJson('/api/students');

        $response->assertSuccessful();
    });

    it('can view student details with read permission', function (): void {
        $this->studentUser->givePermissionTo('View:Student');

        Sanctum::actingAs($this->studentUser);

        $response = $this->getJson("/api/students/{$this->studentRecord->id}");

        $response->assertSuccessful();
    });

    it('can view other student public data with read permission', function (): void {
        $this->studentUser->givePermissionTo('View:Student');

        Sanctum::actingAs($this->studentUser);

        $response = $this->getJson("/api/students/{$this->otherStudentRecord->id}");

        $response->assertSuccessful();
    });

    it('can update own student record with update permission', function (): void {
        $this->studentUser->givePermissionTo('Update:Student');

        Sanctum::actingAs($this->studentUser);

        $response = $this->putJson("/api/students/{$this->studentRecord->id}", [
            'first_name' => 'John Updated',
            'last_name' => 'Doe Updated',
        ]);

        $response->assertSuccessful();

        $this->studentRecord->refresh();
        expect($this->studentRecord->first_name)->toBe('John Updated');
    });

    it('cannot update another student record even with update permission', function (): void {
        $this->studentUser->givePermissionTo('Update:Student');

        Sanctum::actingAs($this->studentUser);

        $response = $this->putJson("/api/students/{$this->otherStudentRecord->id}", [
            'first_name' => 'Hacked',
            'last_name' => 'Student',
        ]);

        $response->assertForbidden();

        // Ensure the other student record was not modified
        $this->otherStudentRecord->refresh();
        expect($this->otherStudentRecord->first_name)->toBe('Jane');
    });

    it('cannot delete another student record even with delete permission', function (): void {
        $this->studentUser->givePermissionTo('Delete:Student');

        Sanctum::actingAs($this->studentUser);

        $response = $this->deleteJson("/api/students/{$this->otherStudentRecord->id}");

        $response->assertForbidden();

        // Ensure the other student record still exists
        expect(Student::find($this->otherStudentRecord->id))->not->toBeNull();
    });

    it('cannot create a new student record with create permission', function (): void {
        $this->studentUser->givePermissionTo('Create:Student');

        Sanctum::actingAs($this->studentUser);

        $response = $this->postJson('/api/students', [
            'student_id' => 'STU-NEW-001',
            'first_name' => 'New',
            'last_name' => 'Student',
            'email' => 'newstudent@example.com',
        ]);

        // Students should not be able to create new student records
        $response->assertForbidden();
    });

    it('is forbidden from accessing students list without permission', function (): void {
        Sanctum::actingAs($this->studentUser);

        $response = $this->getJson('/api/students');

        $response->assertForbidden();
    });

    it('is forbidden from viewing student details without permission', function (): void {
        Sanctum::actingAs($this->studentUser);

        $response = $this->getJson("/api/students/{$this->studentRecord->id}");

        $response->assertForbidden();
    });

    it('is forbidden from updating own record without permission', function (): void {
        Sanctum::actingAs($this->studentUser);

        $response = $this->putJson("/api/students/{$this->studentRecord->id}", [
            'first_name' => 'John Updated',
        ]);

        $response->assertForbidden();
    });
});

describe('API Data Access - Faculty Portal', function (): void {
    beforeEach(function (): void {
        // Create a faculty user
        $this->facultyUser = User::factory()->create([
            'role' => UserRole::Instructor,
            'email' => 'faculty1@example.com',
            'faculty_id_number' => 'FAC001',
            'school_id' => $this->school->id,
        ]);

        // Create a Faculty record linked by email
        $this->facultyRecord = Faculty::factory()->create([
            'email' => $this->facultyUser->email,
            'faculty_id_number' => 'FAC001',
            'first_name' => 'Robert',
            'last_name' => 'Johnson',
            'school_id' => $this->school->id,
        ]);

        // Create another faculty user
        $this->otherFacultyUser = User::factory()->create([
            'role' => UserRole::Professor,
            'email' => 'faculty2@example.com',
            'faculty_id_number' => 'FAC002',
            'school_id' => $this->school->id,
        ]);

        // Create another Faculty record
        $this->otherFacultyRecord = Faculty::factory()->create([
            'email' => $this->otherFacultyUser->email,
            'faculty_id_number' => 'FAC002',
            'first_name' => 'Sarah',
            'last_name' => 'Williams',
            'school_id' => $this->school->id,
        ]);

        // Create some students for faculty to view
        $this->student1 = Student::factory()->create([
            'email' => 'student1@test.com',
            'first_name' => 'Alice',
            'last_name' => 'Brown',
            'document_location_id' => null,
            'school_id' => $this->school->id,
        ]);

        $this->student2 = Student::factory()->create([
            'email' => 'student2@test.com',
            'first_name' => 'Bob',
            'last_name' => 'Davis',
            'document_location_id' => null,
            'school_id' => $this->school->id,
        ]);
    });

    it('can view list of students with read permission', function (): void {
        $this->facultyUser->givePermissionTo('ViewAny:Student');

        Sanctum::actingAs($this->facultyUser);

        $response = $this->getJson('/api/students');

        $response->assertSuccessful();
    });

    it('can view student details with read permission', function (): void {
        $this->facultyUser->givePermissionTo('View:Student');

        Sanctum::actingAs($this->facultyUser);

        $response = $this->getJson("/api/students/{$this->student1->id}");

        $response->assertSuccessful();
    });

    it('can view list of faculty with read permission', function (): void {
        $this->facultyUser->givePermissionTo('ViewAny:Faculty');

        Sanctum::actingAs($this->facultyUser);

        $response = $this->getJson('/api/faculties');

        $response->assertSuccessful();
    });

    it('can view faculty details with read permission', function (): void {
        $this->facultyUser->givePermissionTo('View:Faculty');

        Sanctum::actingAs($this->facultyUser);

        $response = $this->getJson("/api/faculties/{$this->facultyRecord->id}");

        $response->assertSuccessful();
    });

    it('can update own faculty record with update permission', function (): void {
        $this->facultyUser->givePermissionTo('Update:Faculty');

        Sanctum::actingAs($this->facultyUser);

        $response = $this->putJson("/api/faculties/{$this->facultyRecord->id}", [
            'first_name' => 'Robert Updated',
            'last_name' => 'Johnson Updated',
        ]);

        $response->assertSuccessful();

        $this->facultyRecord->refresh();
        expect($this->facultyRecord->first_name)->toBe('Robert Updated');
    });

    it('cannot update another faculty record even with update permission', function (): void {
        $this->facultyUser->givePermissionTo('Update:Faculty');

        Sanctum::actingAs($this->facultyUser);

        $response = $this->putJson("/api/faculties/{$this->otherFacultyRecord->id}", [
            'first_name' => 'Hacked',
            'last_name' => 'Faculty',
        ]);

        $response->assertForbidden();

        // Ensure the other faculty record was not modified
        $this->otherFacultyRecord->refresh();
        expect($this->otherFacultyRecord->first_name)->toBe('Sarah');
    });

    it('cannot delete another faculty record even with delete permission', function (): void {
        $this->facultyUser->givePermissionTo('Delete:Faculty');

        Sanctum::actingAs($this->facultyUser);

        $response = $this->deleteJson("/api/faculties/{$this->otherFacultyRecord->id}");

        $response->assertForbidden();

        // Ensure the other faculty record still exists
        expect(Faculty::find($this->otherFacultyRecord->id))->not->toBeNull();
    });

    it('cannot create a new faculty record with create permission', function (): void {
        $this->facultyUser->givePermissionTo('Create:Faculty');

        Sanctum::actingAs($this->facultyUser);

        $response = $this->postJson('/api/faculties', [
            'faculty_id_number' => 'FAC-NEW-001',
            'first_name' => 'New',
            'last_name' => 'Faculty',
            'email' => 'newfaculty@example.com',
        ]);

        // Faculty should not be able to create new faculty records
        $response->assertForbidden();
    });

    it('cannot update student records even with student update permission', function (): void {
        $this->facultyUser->givePermissionTo('Update:Student');

        Sanctum::actingAs($this->facultyUser);

        $response = $this->putJson("/api/students/{$this->student1->id}", [
            'first_name' => 'Modified',
        ]);

        // Faculty should only have read access to students, not write access
        $response->assertForbidden();
    });

    it('is forbidden from accessing faculty list without permission', function (): void {
        Sanctum::actingAs($this->facultyUser);

        $response = $this->getJson('/api/faculties');

        $response->assertForbidden();
    });

    it('is forbidden from updating own record without permission', function (): void {
        Sanctum::actingAs($this->facultyUser);

        $response = $this->putJson("/api/faculties/{$this->facultyRecord->id}", [
            'first_name' => 'Robert Updated',
        ]);

        $response->assertForbidden();
    });
});

describe('API Data Access - Unauthenticated Users', function (): void {
    beforeEach(function (): void {
        $this->student = Student::factory()->create([
            'email' => 'student@test.com',
            'document_location_id' => null,
        ]);

        $this->faculty = Faculty::factory()->create([
            'email' => 'faculty@test.com',
        ]);
    });

    it('cannot access students list', function (): void {
        $response = $this->getJson('/api/students');

        $response->assertUnauthorized();
    });

    it('cannot access student details', function (): void {
        $response = $this->getJson("/api/students/{$this->student->id}");

        $response->assertUnauthorized();
    });

    it('cannot create student records', function (): void {
        $response = $this->postJson('/api/students', [
            'student_id' => 'STU-001',
            'first_name' => 'Test',
            'last_name' => 'Student',
            'email' => 'test@example.com',
        ]);

        $response->assertUnauthorized();
    });

    it('cannot update student records', function (): void {
        $response = $this->putJson("/api/students/{$this->student->id}", [
            'first_name' => 'Updated',
        ]);

        $response->assertUnauthorized();
    });

    it('cannot delete student records', function (): void {
        $response = $this->deleteJson("/api/students/{$this->student->id}");

        $response->assertUnauthorized();
    });

    it('cannot access faculty list', function (): void {
        $response = $this->getJson('/api/faculties');

        $response->assertUnauthorized();
    });

    it('cannot access faculty details', function (): void {
        $response = $this->getJson("/api/faculties/{$this->faculty->id}");

        $response->assertUnauthorized();
    });

    it('cannot create faculty records', function (): void {
        $response = $this->postJson('/api/faculties', [
            'faculty_id_number' => 'FAC-001',
            'first_name' => 'Test',
            'last_name' => 'Faculty',
            'email' => 'faculty@example.com',
        ]);

        $response->assertUnauthorized();
    });

    it('cannot update faculty records', function (): void {
        $response = $this->putJson("/api/faculties/{$this->faculty->id}", [
            'first_name' => 'Updated',
        ]);

        $response->assertUnauthorized();
    });

    it('cannot delete faculty records', function (): void {
        $response = $this->deleteJson("/api/faculties/{$this->faculty->id}");

        $response->assertUnauthorized();
    });
});

describe('API Data Access - Administrative Users', function (): void {
    beforeEach(function (): void {
        // Create an admin user
        $this->adminUser = User::factory()->create([
            'role' => UserRole::Admin,
            'email' => 'admin@example.com',
            'school_id' => $this->school->id,
        ]);

        $this->student = Student::factory()->create([
            'email' => 'student@test.com',
            'first_name' => 'Test',
            'last_name' => 'Student',
            'document_location_id' => null,
            'school_id' => $this->school->id,
        ]);

        $this->faculty = Faculty::factory()->create([
            'email' => 'faculty@test.com',
            'first_name' => 'Test',
            'last_name' => 'Faculty',
            'school_id' => $this->school->id,
        ]);
    });

    it('can update any student record with update permission', function (): void {
        $this->adminUser->givePermissionTo('Update:Student');

        Sanctum::actingAs($this->adminUser);

        $response = $this->putJson("/api/students/{$this->student->id}", [
            'first_name' => 'Admin Updated',
        ]);

        $response->assertSuccessful();

        $this->student->refresh();
        expect($this->student->first_name)->toBe('Admin Updated');
    });

    it('can update any faculty record with update permission', function (): void {
        $this->adminUser->givePermissionTo('Update:Faculty');

        Sanctum::actingAs($this->adminUser);

        $response = $this->putJson("/api/faculties/{$this->faculty->id}", [
            'first_name' => 'Admin Updated',
        ]);

        $response->assertSuccessful();

        $this->faculty->refresh();
        expect($this->faculty->first_name)->toBe('Admin Updated');
    });

    it('can delete any student record with delete permission', function (): void {
        $this->adminUser->givePermissionTo('Delete:Student');

        Sanctum::actingAs($this->adminUser);

        $response = $this->deleteJson("/api/students/{$this->student->id}");

        $response->assertSuccessful();

        expect(Student::find($this->student->id))->toBeNull();
    });

    it('can delete any faculty record with delete permission', function (): void {
        $this->adminUser->givePermissionTo('Delete:Faculty');

        Sanctum::actingAs($this->adminUser);

        $response = $this->deleteJson("/api/faculties/{$this->faculty->id}");

        $response->assertSuccessful();

        expect(Faculty::find($this->faculty->id))->toBeNull();
    });

    it('can create student records with create permission', function (): void {
        $this->adminUser->givePermissionTo('Create:Student');

        Sanctum::actingAs($this->adminUser);

        $studentData = array_merge(Student::factory()->make([
            'email' => 'admincreated@example.com',
            'first_name' => 'Admin Created',
        ])->toArray(), [
            'remarks' => 'Created by an authorized administrator',
            'profile_url' => 'https://example.com/student-profile',
            'student_contact_id' => 1,
            'student_parent_info' => 1,
            'student_education_id' => 1,
            'student_personal_id' => 1,
            'document_location_id' => 1,
            'deleted_at' => now()->toDateTimeString(),
            'year_graduated' => now()->year,
            'special_order' => 'TEST-SO-001',
            'issued_date' => now()->toDateString(),
            'subject_enrolled' => ['TEST101'],
            'user_id' => $this->adminUser->id,
            'ethnicity' => 'Not specified',
            'indigenous_group' => 'Not applicable',
            'withdrawal_date' => now()->toDateString(),
            'withdrawal_reason' => 'Not applicable',
            'attrition_category' => 'other',
            'dropout_date' => now()->toDateString(),
            'employment_status' => 'unemployed',
            'employer_name' => 'Not applicable',
            'job_position' => 'Not applicable',
            'employment_date' => now()->toDateString(),
            'employed_by_institution' => false,
            'scholarship_type' => 'none',
            'scholarship_details' => 'Not applicable',
        ]);

        $response = $this->postJson('/api/students', $studentData);

        $response->assertSuccessful();
    });

    it('can create faculty records with create permission', function (): void {
        $this->adminUser->givePermissionTo('Create:Faculty');

        Sanctum::actingAs($this->adminUser);

        $facultyData = array_merge(Faculty::factory()->make([
            'email' => 'admincreated@example.com',
            'first_name' => 'Admin Created',
        ])->toArray(), [
            'middle_name' => 'Test',
            'office_hours' => 'Monday 09:00-11:00',
            'biography' => 'Test faculty biography',
            'education' => 'Test degree',
            'courses_taught' => 'TEST101',
            'photo_url' => 'https://example.com/faculty-photo.jpg',
            'password' => 'password123',
            'remember_token' => 'test-token',
            'email_verified_at' => now()->toDateTimeString(),
            'faculty_code' => 'FAC-ADMIN-001',
            'full_name' => 'Admin Created Test',
        ]);

        $response = $this->postJson('/api/faculties', $facultyData);

        $response->assertSuccessful();
    });
});
