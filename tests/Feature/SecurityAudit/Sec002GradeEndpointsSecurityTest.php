<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * SEC-002 · Grade endpoints accept writes from any authenticated caller.
 *
 * Verifies that:
 * 1. Grade mutations (grades, finalize, verify, bulk-grades, update, destroy, forceDestroy)
 *    are forbidden to unauthorized callers (e.g. students, unrelated faculty).
 * 2. Only authorized faculty who own the class or callers holding Update:ClassEnrollment
 *    (or administrative roles) can perform grade/enrollment writes.
 * 3. Total average and grade fields are strictly validated through the request validator.
 * 4. General updates cannot mass-assign finalized or verified attributes.
 */
beforeEach(function (): void {
    Permission::findOrCreate('Update:ClassEnrollment', 'web');

    // API requests are tenant-scoped; every fixture belongs to this school.
    $this->school = School::factory()->create();

    $this->faculty = Faculty::factory()->create(['school_id' => $this->school->id]);
    $this->facultyUser = User::factory()->create([
        'role' => UserRole::Instructor,
        'email' => $this->faculty->email,
        'record_id' => $this->faculty->id,
        'faculty_id_number' => $this->faculty->faculty_id_number,
        'school_id' => $this->school->id,
    ]);

    $this->otherFaculty = Faculty::factory()->create(['school_id' => $this->school->id]);
    $this->otherFacultyUser = User::factory()->create([
        'role' => UserRole::Instructor,
        'email' => $this->otherFaculty->email,
        'record_id' => $this->otherFaculty->id,
        'faculty_id_number' => $this->otherFaculty->faculty_id_number,
        'school_id' => $this->school->id,
    ]);

    $this->studentUser = User::factory()->create([
        'role' => UserRole::Student,
        'school_id' => $this->school->id,
    ]);

    $this->class = Classes::factory()->create([
        'faculty_id' => $this->faculty->id,
        'school_id' => $this->school->id,
    ]);

    $this->student = Student::factory()->create(['school_id' => $this->school->id]);
    $this->enrollment = ClassEnrollment::factory()->create([
        'class_id' => $this->class->id,
        'student_id' => $this->student->id,
        'school_id' => $this->school->id,
        'prelim_grade' => null,
        'midterm_grade' => null,
        'finals_grade' => null,
        'total_average' => null,
        'is_grades_finalized' => false,
        'is_grades_verified' => false,
    ]);
});

it('blocks students from PATCHing arbitrary grades onto enrollments', function (): void {
    $this->actingAs($this->studentUser, 'sanctum')
        ->patchJson("/api/class-enrollments/{$this->enrollment->id}/grades", [
            'prelim_grade' => 100,
            'midterm_grade' => 100,
            'finals_grade' => 100,
            'total_average' => 100,
        ])
        ->assertForbidden();

    expect($this->enrollment->fresh()->total_average)->toBeNull();
});

it('blocks students from finalizing and verifying grades', function (): void {
    $this->enrollment->update([
        'prelim_grade' => 85,
        'midterm_grade' => 90,
        'finals_grade' => 95,
    ]);

    $this->actingAs($this->studentUser, 'sanctum')
        ->postJson("/api/class-enrollments/{$this->enrollment->id}/finalize")
        ->assertForbidden();

    expect($this->enrollment->fresh()->is_grades_finalized)->toBeFalse();

    $this->enrollment->update(['is_grades_finalized' => true]);

    $this->actingAs($this->studentUser, 'sanctum')
        ->postJson("/api/class-enrollments/{$this->enrollment->id}/verify")
        ->assertForbidden();

    expect($this->enrollment->fresh()->is_grades_verified)->toBeFalse();
});

it('blocks students from mass-overwriting a class roster of grades', function (): void {
    $this->actingAs($this->studentUser, 'sanctum')
        ->patchJson("/api/class-enrollments/class/{$this->class->id}/bulk-grades", [
            'grades' => [[
                'enrollment_id' => $this->enrollment->id,
                'prelim_grade' => 100,
                'midterm_grade' => 100,
                'finals_grade' => 100,
            ]],
        ])
        ->assertForbidden();

    expect($this->enrollment->fresh()->total_average)->toBeNull();
});

it('blocks students from general update and deletion endpoints', function (): void {
    $this->actingAs($this->studentUser, 'sanctum')
        ->putJson("/api/class-enrollments/{$this->enrollment->id}", [
            'remarks' => 'Modified by student',
        ])
        ->assertForbidden();

    $this->actingAs($this->studentUser, 'sanctum')
        ->deleteJson("/api/class-enrollments/{$this->enrollment->id}")
        ->assertForbidden();

    $this->actingAs($this->studentUser, 'sanctum')
        ->deleteJson("/api/class-enrollments/{$this->enrollment->id}/force")
        ->assertForbidden();

    expect(ClassEnrollment::whereKey($this->enrollment->id)->exists())->toBeTrue();
});

it('blocks faculty from writing to classes they do not teach', function (): void {
    $this->actingAs($this->otherFacultyUser, 'sanctum')
        ->patchJson("/api/class-enrollments/{$this->enrollment->id}/grades", [
            'prelim_grade' => 90,
        ])
        ->assertForbidden();

    $this->actingAs($this->otherFacultyUser, 'sanctum')
        ->patchJson("/api/class-enrollments/class/{$this->class->id}/bulk-grades", [
            'grades' => [[
                'enrollment_id' => $this->enrollment->id,
                'prelim_grade' => 90,
            ]],
        ])
        ->assertForbidden();

    $this->actingAs($this->otherFacultyUser, 'sanctum')
        ->postJson("/api/class-enrollments/{$this->enrollment->id}/finalize")
        ->assertForbidden();

    $this->enrollment->update([
        'is_grades_finalized' => true,
        'is_grades_verified' => false,
        'verified_by' => null,
        'verified_at' => null,
        'verification_notes' => null,
    ]);

    $this->actingAs($this->otherFacultyUser, 'sanctum')
        ->postJson("/api/class-enrollments/{$this->enrollment->id}/verify", [
            'verification_notes' => 'Unauthorized verification',
        ])
        ->assertForbidden();

    $fresh = $this->enrollment->fresh();
    expect($fresh->is_grades_verified)->toBeFalse()
        ->and($fresh->verified_by)->toBeNull()
        ->and($fresh->verified_at)->toBeNull()
        ->and($fresh->verification_notes)->toBeNull();

    $this->actingAs($this->otherFacultyUser, 'sanctum')
        ->deleteJson("/api/class-enrollments/{$this->enrollment->id}")
        ->assertForbidden();
});

it('blocks faculty from moving an enrollment to a class they do not teach', function (): void {
    $otherClass = Classes::factory()->create([
        'faculty_id' => $this->otherFaculty->id,
        'school_id' => $this->school->id,
    ]);

    $this->actingAs($this->facultyUser, 'sanctum')
        ->putJson("/api/class-enrollments/{$this->enrollment->id}", [
            'class_id' => $otherClass->id,
        ])
        ->assertForbidden();

    expect($this->enrollment->fresh()->class_id)->toBe($this->class->id);
});

it('rejects faculty whose record_id does not match their authentic email identity', function (): void {
    $spoofedUser = User::factory()->create([
        'role' => UserRole::Instructor,
        'email' => 'attacker@example.com',
        'record_id' => $this->faculty->id,
        'school_id' => $this->school->id,
    ]);

    $this->actingAs($spoofedUser, 'sanctum')
        ->patchJson("/api/class-enrollments/{$this->enrollment->id}/grades", [
            'prelim_grade' => 95,
        ])
        ->assertForbidden();
});

it('reports enrollments belonging to another class as errors in bulk update', function (): void {
    $otherClass = Classes::factory()->create([
        'faculty_id' => $this->otherFaculty->id,
        'school_id' => $this->school->id,
    ]);
    $otherEnrollment = ClassEnrollment::factory()->create([
        'class_id' => $otherClass->id,
        'school_id' => $this->school->id,
        'prelim_grade' => null,
    ]);

    $response = $this->actingAs($this->facultyUser, 'sanctum')
        ->patchJson("/api/class-enrollments/class/{$this->class->id}/bulk-grades", [
            'grades' => [
                [
                    'enrollment_id' => $this->enrollment->id,
                    'prelim_grade' => 88,
                ],
                [
                    'enrollment_id' => $otherEnrollment->id,
                    'prelim_grade' => 99,
                ],
            ],
        ]);

    $response->assertOk()
        ->assertJsonPath('data.total_updated', 1)
        ->assertJsonPath('data.total_errors', 1);

    expect((float) $this->enrollment->fresh()->prelim_grade)->toBe(88.0)
        ->and($otherEnrollment->fresh()->prelim_grade)->toBeNull();
});

it('blocks executive read-only roles and student affairs from grade writes', function (): void {
    $president = User::factory()->create(['role' => UserRole::President, 'school_id' => $this->school->id]);
    $vicePresident = User::factory()->create(['role' => UserRole::VicePresident, 'school_id' => $this->school->id]);
    $studentAffairs = User::factory()->create(['role' => UserRole::StudentAffairsOfficer, 'school_id' => $this->school->id]);

    foreach ([$president, $vicePresident, $studentAffairs] as $user) {
        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/class-enrollments/{$this->enrollment->id}/grades", [
                'prelim_grade' => 90,
            ])
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/class-enrollments/{$this->enrollment->id}/finalize")
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/class-enrollments/{$this->enrollment->id}/force")
            ->assertForbidden();
    }
});

it('blocks instructors and Update:ClassEnrollment holders from force delete and restore without dedicated permissions', function (): void {
    $staff = User::factory()->create(['school_id' => $this->school->id]);
    $staff->givePermissionTo('Update:ClassEnrollment');

    $this->enrollment->delete();

    $this->actingAs($this->facultyUser, 'sanctum')
        ->postJson("/api/class-enrollments/{$this->enrollment->id}/restore")
        ->assertForbidden();

    $this->actingAs($this->facultyUser, 'sanctum')
        ->deleteJson("/api/class-enrollments/{$this->enrollment->id}/force")
        ->assertForbidden();

    $this->actingAs($staff, 'sanctum')
        ->postJson("/api/class-enrollments/{$this->enrollment->id}/restore")
        ->assertForbidden();

    $this->actingAs($staff, 'sanctum')
        ->deleteJson("/api/class-enrollments/{$this->enrollment->id}/force")
        ->assertForbidden();
});

it('allows callers with Create:ClassEnrollment to store an enrollment', function (): void {
    Permission::findOrCreate('Create:ClassEnrollment', 'web');
    $creator = User::factory()->create(['school_id' => $this->school->id]);
    $creator->givePermissionTo('Create:ClassEnrollment');

    $student = Student::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($creator, 'sanctum')
        ->postJson('/api/class-enrollments', [
            'class_id' => $this->class->id,
            'student_id' => $student->id,
        ])
        ->assertCreated();
});

it('allows assigned faculty to update and finalize grades for their class', function (): void {
    $response = $this->actingAs($this->facultyUser, 'sanctum')
        ->patchJson("/api/class-enrollments/{$this->enrollment->id}/grades", [
            'prelim_grade' => 80,
            'midterm_grade' => 90,
            'finals_grade' => 100,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.prelim_grade', '80.00');

    $fresh = $this->enrollment->fresh();
    expect((float) $fresh->prelim_grade)->toBe(80.0)
        ->and((float) $fresh->midterm_grade)->toBe(90.0)
        ->and((float) $fresh->finals_grade)->toBe(100.0)
        ->and((float) $fresh->total_average)->toBe(91.0);

    $this->actingAs($this->facultyUser, 'sanctum')
        ->postJson("/api/class-enrollments/{$this->enrollment->id}/finalize")
        ->assertOk();

    expect($this->enrollment->fresh()->is_grades_finalized)->toBeTrue();
});

it('allows users holding Update:ClassEnrollment permission to update and verify grades', function (): void {
    $staff = User::factory()->create(['school_id' => $this->school->id]);
    $staff->givePermissionTo('Update:ClassEnrollment');

    $this->enrollment->update([
        'prelim_grade' => 85,
        'midterm_grade' => 85,
        'finals_grade' => 85,
        'total_average' => 85,
        'is_grades_finalized' => true,
    ]);

    $this->actingAs($staff, 'sanctum')
        ->postJson("/api/class-enrollments/{$this->enrollment->id}/verify", [
            'verification_notes' => 'Audited by staff',
        ])
        ->assertOk();

    $fresh = $this->enrollment->fresh();
    expect($fresh->is_grades_verified)->toBeTrue()
        ->and($fresh->verified_by)->toBe($staff->id);
});

it('validates total_average against numeric constraints and prevents validator bypass', function (): void {
    $this->actingAs($this->facultyUser, 'sanctum')
        ->patchJson("/api/class-enrollments/{$this->enrollment->id}/grades", [
            'total_average' => 9999,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['total_average']);

    $this->actingAs($this->facultyUser, 'sanctum')
        ->patchJson("/api/class-enrollments/{$this->enrollment->id}/grades", [
            'total_average' => 'not-a-number',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['total_average']);
});

it('prevents mass assignment of is_grades_finalized and verified_by via general update', function (): void {
    $this->actingAs($this->facultyUser, 'sanctum')
        ->putJson("/api/class-enrollments/{$this->enrollment->id}", [
            'remarks' => 'Valid remarks',
            'is_grades_finalized' => true,
            'verified_by' => $this->facultyUser->id,
        ])
        ->assertOk();

    $fresh = $this->enrollment->fresh();
    expect($fresh->remarks)->toBe('Valid remarks')
        ->and($fresh->is_grades_finalized)->toBeFalse()
        ->and($fresh->verified_by)->toBeNull();
});

it('requires authentication for grade endpoints', function (): void {
    $this->patchJson("/api/class-enrollments/{$this->enrollment->id}/grades", [
        'prelim_grade' => 90,
    ])->assertUnauthorized();

    $this->postJson("/api/class-enrollments/{$this->enrollment->id}/finalize")
        ->assertUnauthorized();

    $this->postJson("/api/class-enrollments/{$this->enrollment->id}/verify")
        ->assertUnauthorized();

    $this->patchJson("/api/class-enrollments/class/{$this->class->id}/bulk-grades", [
        'grades' => [],
    ])->assertUnauthorized();
});
