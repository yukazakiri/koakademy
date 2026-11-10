<?php

declare(strict_types=1);

use App\Enums\ClassPostType;
use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\ClassPost;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;

/**
 * SEC-013 · API list and detail endpoints expose cross-student and cross-faculty data.
 *
 * Verifies that:
 * 1. /api/enrollments list and detail endpoints scope strictly to the actor's own student record.
 * 2. Unprivileged callers cannot access other students' schedules, assessments, or administrative statistics.
 * 3. /api/class-enrollments scopes to the student's own records, faculty's own classes, or administrative roles.
 * 4. Class posts CRUD and detail endpoints restrict viewing and mutations to assigned faculty and enrolled students.
 * 5. /api/faculty endpoints forbid non-faculty callers and prevent cross-faculty class, student, and grade access.
 */
beforeEach(function (): void {
    $this->school = School::factory()->create();

    // Student A & Student B
    $this->studentA = Student::factory()->create(['school_id' => $this->school->id]);
    $this->userStudentA = User::factory()->create([
        'role' => UserRole::Student,
        'email' => $this->studentA->email,
        'record_id' => $this->studentA->id,
        'school_id' => $this->school->id,
    ]);
    $this->studentA->update(['user_id' => $this->userStudentA->id]);

    $this->studentB = Student::factory()->create(['school_id' => $this->school->id]);
    $this->userStudentB = User::factory()->create([
        'role' => UserRole::Student,
        'email' => $this->studentB->email,
        'record_id' => $this->studentB->id,
        'school_id' => $this->school->id,
    ]);
    $this->studentB->update(['user_id' => $this->userStudentB->id]);

    // Faculty A & Faculty B
    $this->facultyA = Faculty::factory()->create(['school_id' => $this->school->id]);
    $this->userFacultyA = User::factory()->create([
        'role' => UserRole::Instructor,
        'email' => $this->facultyA->email,
        'record_id' => $this->facultyA->id,
        'faculty_id_number' => $this->facultyA->faculty_id_number,
        'school_id' => $this->school->id,
    ]);

    $this->facultyB = Faculty::factory()->create(['school_id' => $this->school->id]);
    $this->userFacultyB = User::factory()->create([
        'role' => UserRole::Instructor,
        'email' => $this->facultyB->email,
        'record_id' => $this->facultyB->id,
        'faculty_id_number' => $this->facultyB->faculty_id_number,
        'school_id' => $this->school->id,
    ]);

    // Registrar & Admin
    $this->userRegistrar = User::factory()->create([
        'role' => UserRole::Registrar,
        'school_id' => $this->school->id,
    ]);
    foreach ([
        'ViewAny:StudentEnrollment',
        'View:StudentEnrollment',
        'ViewAny:ClassEnrollment',
        'View:ClassEnrollment',
        'ViewAny:ClassPost',
        'View:ClassPost',
    ] as $perm) {
        Spatie\Permission\Models\Permission::findOrCreate($perm, 'web');
    }
    $this->userRegistrar->givePermissionTo([
        'ViewAny:StudentEnrollment',
        'View:StudentEnrollment',
        'ViewAny:ClassEnrollment',
        'View:ClassEnrollment',
        'ViewAny:ClassPost',
        'View:ClassPost',
    ]);
    $this->userAdmin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => $this->school->id,
    ]);

    // Classes
    $this->classA = Classes::factory()->create([
        'school_id' => $this->school->id,
        'faculty_id' => $this->facultyA->id,
    ]);
    $this->classB = Classes::factory()->create([
        'school_id' => $this->school->id,
        'faculty_id' => $this->facultyB->id,
    ]);

    // Student Enrollments
    $this->course = Course::factory()->create(['school_id' => $this->school->id]);
    $this->enrollmentA = StudentEnrollment::factory()->create([
        'school_id' => $this->school->id,
        'student_id' => $this->studentA->id,
        'course_id' => $this->course->id,
    ]);
    $this->enrollmentB = StudentEnrollment::factory()->create([
        'school_id' => $this->school->id,
        'student_id' => $this->studentB->id,
        'course_id' => $this->course->id,
    ]);

    // Class Enrollments
    $this->classEnrollmentA = ClassEnrollment::factory()->create([
        'school_id' => $this->school->id,
        'class_id' => $this->classA->id,
        'student_id' => $this->studentA->id,
        'prelim_grade' => 88,
        'midterm_grade' => 90,
        'finals_grade' => 92,
        'total_average' => 90,
    ]);
    $this->classEnrollmentB = ClassEnrollment::factory()->create([
        'school_id' => $this->school->id,
        'class_id' => $this->classB->id,
        'student_id' => $this->studentB->id,
        'prelim_grade' => 75,
        'midterm_grade' => 78,
        'finals_grade' => 80,
        'total_average' => 77.67,
    ]);

    // Class Posts
    $this->postA = ClassPost::create([
        'class_id' => $this->classA->id,
        'title' => 'Lecture Notes for Class A',
        'type' => ClassPostType::Assignment,
    ]);
    $this->postB = ClassPost::create([
        'class_id' => $this->classB->id,
        'title' => 'Exam Prep for Class B',
        'type' => ClassPostType::Announcement,
    ]);
});

/*
|--------------------------------------------------------------------------
| 1. StudentEnrollment API (/api/enrollments) Scoping & Authorization
|--------------------------------------------------------------------------
*/

it('scopes /api/enrollments index strictly to the authenticated student', function (): void {
    $response = $this->actingAs($this->userStudentA, 'sanctum')
        ->getJson('/api/enrollments');

    $response->assertOk();
    $data = $response->json('data');

    expect($data)->not->toBeEmpty()
        ->and(collect($data)->pluck('id')->all())->toContain($this->enrollmentA->id)
        ->and(collect($data)->pluck('id')->all())->not->toContain($this->enrollmentB->id);
});

it('prevents students from circumventing enrollment scoping via query filters', function (): void {
    $response = $this->actingAs($this->userStudentA, 'sanctum')
        ->getJson("/api/enrollments?student_id={$this->studentB->id}");

    $response->assertOk();
    $data = $response->json('data');

    expect($data)->toBeEmpty();
});

it('allows registrar to view all enrollments across students', function (): void {
    $response = $this->actingAs($this->userRegistrar, 'sanctum')
        ->getJson('/api/enrollments');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($this->enrollmentA->id)
        ->and($ids)->toContain($this->enrollmentB->id);
});

it('allows student to view own enrollment details but blocks viewing other student enrollment', function (): void {
    $this->actingAs($this->userStudentA, 'sanctum')
        ->getJson("/api/enrollments/{$this->enrollmentA->id}")
        ->assertOk();

    $this->actingAs($this->userStudentA, 'sanctum')
        ->getJson("/api/enrollments/{$this->enrollmentB->id}")
        ->assertForbidden();
});

it('allows student to view own schedule and assessment but blocks other student records', function (): void {
    $this->actingAs($this->userStudentA, 'sanctum')
        ->getJson("/api/enrollments/{$this->enrollmentA->id}/schedule")
        ->assertOk();

    $this->actingAs($this->userStudentA, 'sanctum')
        ->getJson("/api/enrollments/{$this->enrollmentB->id}/schedule")
        ->assertForbidden();

    $this->actingAs($this->userStudentA, 'sanctum')
        ->getJson("/api/enrollments/{$this->enrollmentA->id}/assessment")
        ->assertOk();

    $this->actingAs($this->userStudentA, 'sanctum')
        ->getJson("/api/enrollments/{$this->enrollmentB->id}/assessment")
        ->assertForbidden();
});

it('blocks students from enrollment statistics summary and mutation actions', function (): void {
    $this->actingAs($this->userStudentA, 'sanctum')
        ->getJson('/api/enrollments/statistics/summary')
        ->assertForbidden();

    $this->actingAs($this->userStudentA, 'sanctum')
        ->deleteJson("/api/enrollments/{$this->enrollmentA->id}")
        ->assertForbidden();

    $this->actingAs($this->userStudentA, 'sanctum')
        ->putJson("/api/enrollments/{$this->enrollmentA->id}", ['status' => 'enrolled'])
        ->assertForbidden();

    $this->actingAs($this->userStudentA, 'sanctum')
        ->postJson("/api/enrollments/{$this->enrollmentA->id}/reopen", [
            'reason' => 'Unauthorized reopen request',
            'idempotency_key' => 'idemp-12345',
        ])
        ->assertForbidden();

    $this->actingAs($this->userRegistrar, 'sanctum')
        ->getJson('/api/enrollments/statistics/summary')
        ->assertOk();
});

/*
|--------------------------------------------------------------------------
| 2. ClassEnrollment API (/api/class-enrollments) Scoping & Authorization
|--------------------------------------------------------------------------
*/

it('scopes /api/class-enrollments index to student own records', function (): void {
    $response = $this->actingAs($this->userStudentA, 'sanctum')
        ->getJson('/api/class-enrollments');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($this->classEnrollmentA->id)
        ->and($ids)->not->toContain($this->classEnrollmentB->id);
});

it('scopes /api/class-enrollments index to faculty assigned classes', function (): void {
    $response = $this->actingAs($this->userFacultyA, 'sanctum')
        ->getJson('/api/class-enrollments');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($this->classEnrollmentA->id)
        ->and($ids)->not->toContain($this->classEnrollmentB->id);
});

it('allows registrar to view all class enrollments', function (): void {
    $response = $this->actingAs($this->userRegistrar, 'sanctum')
        ->getJson('/api/class-enrollments');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($this->classEnrollmentA->id)
        ->and($ids)->toContain($this->classEnrollmentB->id);
});

it('blocks students and unrelated faculty from viewing class enrollment details', function (): void {
    // Student A viewing own enrollment -> OK
    $this->actingAs($this->userStudentA, 'sanctum')
        ->getJson("/api/class-enrollments/{$this->classEnrollmentA->id}")
        ->assertOk();

    // Student A viewing Student B enrollment -> 403
    $this->actingAs($this->userStudentA, 'sanctum')
        ->getJson("/api/class-enrollments/{$this->classEnrollmentB->id}")
        ->assertForbidden();

    // Faculty A viewing Class A enrollment -> OK
    $this->actingAs($this->userFacultyA, 'sanctum')
        ->getJson("/api/class-enrollments/{$this->classEnrollmentA->id}")
        ->assertOk();

    // Faculty A viewing Class B enrollment -> 403
    $this->actingAs($this->userFacultyA, 'sanctum')
        ->getJson("/api/class-enrollments/{$this->classEnrollmentB->id}")
        ->assertForbidden();
});

it('blocks unauthorized access to /api/class-enrollments/class/{classId}', function (): void {
    // Assigned faculty -> OK
    $this->actingAs($this->userFacultyA, 'sanctum')
        ->getJson("/api/class-enrollments/class/{$this->classA->id}")
        ->assertOk();

    // Unrelated faculty -> 403
    $this->actingAs($this->userFacultyA, 'sanctum')
        ->getJson("/api/class-enrollments/class/{$this->classB->id}")
        ->assertForbidden();

    // Student -> 403
    $this->actingAs($this->userStudentA, 'sanctum')
        ->getJson("/api/class-enrollments/class/{$this->classA->id}")
        ->assertForbidden();
});

it('blocks unauthorized access to class grade statistics', function (): void {
    $this->actingAs($this->userFacultyA, 'sanctum')
        ->getJson("/api/class-enrollments/class/{$this->classA->id}/statistics")
        ->assertOk();

    $this->actingAs($this->userFacultyA, 'sanctum')
        ->getJson("/api/class-enrollments/class/{$this->classB->id}/statistics")
        ->assertForbidden();

    $this->actingAs($this->userStudentA, 'sanctum')
        ->getJson("/api/class-enrollments/class/{$this->classA->id}/statistics")
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| 3. ClassPost API (/api/class-posts) Scoping & Authorization
|--------------------------------------------------------------------------
*/

it('scopes /api/class-posts index to enrolled classes for students and assigned classes for faculty', function (): void {
    // Student A only enrolled in Class A
    $response = $this->actingAs($this->userStudentA, 'sanctum')
        ->getJson('/api/class-posts');

    $response->assertOk();
    $studentIds = collect($response->json('data'))->pluck('id')->all();
    expect($studentIds)->toContain($this->postA->id)
        ->and($studentIds)->not->toContain($this->postB->id);

    // Faculty A only teaches Class A
    $response = $this->actingAs($this->userFacultyA, 'sanctum')
        ->getJson('/api/class-posts');

    $response->assertOk();
    $facultyIds = collect($response->json('data'))->pluck('id')->all();
    expect($facultyIds)->toContain($this->postA->id)
        ->and($facultyIds)->not->toContain($this->postB->id);

    // Registrar sees both
    $response = $this->actingAs($this->userRegistrar, 'sanctum')
        ->getJson('/api/class-posts');

    $response->assertOk();
    $registrarIds = collect($response->json('data'))->pluck('id')->all();
    expect($registrarIds)->toContain($this->postA->id)
        ->and($registrarIds)->toContain($this->postB->id);
});

it('protects /api/class-posts detail and by-class endpoints against unauthorized actors', function (): void {
    // Student A enrolled in Class A -> OK
    $this->actingAs($this->userStudentA, 'sanctum')
        ->getJson("/api/class-posts/{$this->postA->id}")
        ->assertOk();

    $this->actingAs($this->userStudentA, 'sanctum')
        ->getJson("/api/class-posts/class/{$this->classA->id}")
        ->assertOk();

    // Student A not enrolled in Class B -> 403
    $this->actingAs($this->userStudentA, 'sanctum')
        ->getJson("/api/class-posts/{$this->postB->id}")
        ->assertForbidden();

    $this->actingAs($this->userStudentA, 'sanctum')
        ->getJson("/api/class-posts/class/{$this->classB->id}")
        ->assertForbidden();

    // Faculty A not teaching Class B -> 403
    $this->actingAs($this->userFacultyA, 'sanctum')
        ->getJson("/api/class-posts/{$this->postB->id}")
        ->assertForbidden();
});

it('blocks students and unrelated faculty from mutating class posts', function (): void {
    // Student cannot create class post
    $this->actingAs($this->userStudentA, 'sanctum')
        ->postJson('/api/class-posts', [
            'class_id' => $this->classA->id,
            'title' => 'Student Post Attempt',
            'type' => ClassPostType::Announcement->value,
        ])
        ->assertForbidden();

    // Unrelated faculty cannot create post in another class
    $this->actingAs($this->userFacultyB, 'sanctum')
        ->postJson('/api/class-posts', [
            'class_id' => $this->classA->id,
            'title' => 'Faculty B Post in Class A',
            'type' => ClassPostType::Announcement->value,
        ])
        ->assertForbidden();

    // Assigned faculty CAN create post in own class
    $this->actingAs($this->userFacultyA, 'sanctum')
        ->postJson('/api/class-posts', [
            'class_id' => $this->classA->id,
            'title' => 'Faculty A Valid Post',
            'type' => ClassPostType::Announcement->value,
        ])
        ->assertCreated();

    // Student cannot delete class post
    $this->actingAs($this->userStudentA, 'sanctum')
        ->deleteJson("/api/class-posts/{$this->postA->id}")
        ->assertForbidden();

    // Unrelated faculty cannot update class post
    $this->actingAs($this->userFacultyB, 'sanctum')
        ->putJson("/api/class-posts/{$this->postA->id}", [
            'title' => 'Modified by Faculty B',
        ])
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| 4. Faculty Mobile API (/api/faculty) Scoping & Authorization
|--------------------------------------------------------------------------
*/

it('blocks non-faculty callers from /api/faculty endpoints', function (): void {
    $this->actingAs($this->userStudentA, 'sanctum')
        ->getJson('/api/faculty/classes')
        ->assertForbidden();

    $this->actingAs($this->userStudentA, 'sanctum')
        ->getJson('/api/faculty/students')
        ->assertForbidden();

    $this->actingAs($this->userStudentA, 'sanctum')
        ->getJson("/api/faculty/classes/{$this->classA->id}/students")
        ->assertForbidden();

    $this->actingAs($this->userStudentA, 'sanctum')
        ->patchJson("/api/faculty/classes/{$this->classA->id}/enrollments/{$this->classEnrollmentA->id}/grades", [
            'prelim_grade' => 99,
        ])
        ->assertForbidden();
});

it('blocks faculty from accessing another faculty class student roster', function (): void {
    // Faculty A accessing own class students -> OK
    $this->actingAs($this->userFacultyA, 'sanctum')
        ->getJson("/api/faculty/classes/{$this->classA->id}/students")
        ->assertOk();

    // Faculty A accessing Faculty B class students -> 403
    $this->actingAs($this->userFacultyA, 'sanctum')
        ->getJson("/api/faculty/classes/{$this->classB->id}/students")
        ->assertForbidden();
});

it('scopes /api/faculty/students to only students in the faculty own classes', function (): void {
    $response = $this->actingAs($this->userFacultyA, 'sanctum')
        ->getJson('/api/faculty/students');

    $response->assertOk();
    $data = $response->json('data');
    $studentIds = collect($data)->pluck('student_id')->all();

    expect($studentIds)->toContain($this->studentA->student_id)
        ->and($studentIds)->not->toContain($this->studentB->student_id);
});

it('blocks faculty from mutating grades for classes they do not teach', function (): void {
    $this->actingAs($this->userFacultyA, 'sanctum')
        ->patchJson("/api/faculty/classes/{$this->classB->id}/enrollments/{$this->classEnrollmentB->id}/grades", [
            'prelim_grade' => 95,
        ])
        ->assertForbidden();

    expect((float) $this->classEnrollmentB->fresh()->prelim_grade)->toBe(75.0);
});

it('blocks faculty from mutating grades with an enrollment belonging to another class', function (): void {
    $this->actingAs($this->userFacultyA, 'sanctum')
        ->patchJson("/api/faculty/classes/{$this->classA->id}/enrollments/{$this->classEnrollmentB->id}/grades", [
            'prelim_grade' => 95,
        ])
        ->assertForbidden();

    expect((float) $this->classEnrollmentB->fresh()->prelim_grade)->toBe(75.0);
});
