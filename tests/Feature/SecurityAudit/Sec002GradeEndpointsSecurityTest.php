<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Faculty;
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

    $this->faculty = Faculty::factory()->create();
    $this->facultyUser = User::factory()->create([
        'role' => UserRole::Instructor,
        'record_id' => $this->faculty->id,
    ]);

    $this->otherFaculty = Faculty::factory()->create();
    $this->otherFacultyUser = User::factory()->create([
        'role' => UserRole::Instructor,
        'record_id' => $this->otherFaculty->id,
    ]);

    $this->studentUser = User::factory()->create([
        'role' => UserRole::Student,
    ]);

    $this->class = Classes::factory()->create([
        'faculty_id' => $this->faculty->id,
    ]);

    $this->student = Student::factory()->create();
    $this->enrollment = ClassEnrollment::factory()->create([
        'class_id' => $this->class->id,
        'student_id' => $this->student->id,
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

    $this->actingAs($this->otherFacultyUser, 'sanctum')
        ->deleteJson("/api/class-enrollments/{$this->enrollment->id}")
        ->assertForbidden();
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
    $staff = User::factory()->create();
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
