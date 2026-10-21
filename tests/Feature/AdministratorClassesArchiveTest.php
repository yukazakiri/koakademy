<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

function classArchiveAdmin(): User
{
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    return $admin;
}

function classArchiveClass(): Classes
{
    $currentYear = date('Y');

    return Classes::factory()->create([
        'school_year' => $currentYear.' - '.($currentYear + 1),
        'semester' => 1,
        'classification' => 'college',
    ]);
}

it('archives a class instead of deleting it', function (): void {
    $admin = classArchiveAdmin();
    $this->actingAs($admin);

    $class = classArchiveClass();

    $this->delete(route('administrators.classes.destroy', ['class' => $class->id]))
        ->assertRedirect(route('administrators.classes.index'));

    $this->assertSoftDeleted('classes', ['id' => $class->id]);

    // The archived class drops out of the live list.
    $this->get(route('administrators.classes.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/classes/index', false)
            ->has('classes.data', 0)
        );
});

it('archives the schedules and enrolments of an archived class', function (): void {
    $admin = classArchiveAdmin();
    $this->actingAs($admin);

    $class = classArchiveClass();

    $schedule = Schedule::factory()->create(['class_id' => $class->id]);
    $enrollment = ClassEnrollment::factory()->create([
        'class_id' => $class->id,
        'student_id' => Student::factory()->create([
            'course_id' => Course::factory()->create()->id,
            'document_location_id' => null,
        ])->id,
    ]);

    $this->delete(route('administrators.classes.destroy', ['class' => $class->id]))->assertRedirect();

    $this->assertSoftDeleted('schedule', ['id' => $schedule->id]);
    $this->assertSoftDeleted('class_enrollments', ['id' => $enrollment->id]);
});

it('restores an archived class with its schedules and enrolments', function (): void {
    $admin = classArchiveAdmin();
    $this->actingAs($admin);

    $class = classArchiveClass();

    $schedule = Schedule::factory()->create(['class_id' => $class->id]);
    $enrollment = ClassEnrollment::factory()->create([
        'class_id' => $class->id,
        'student_id' => Student::factory()->create([
            'course_id' => Course::factory()->create()->id,
            'document_location_id' => null,
        ])->id,
    ]);

    $this->delete(route('administrators.classes.destroy', ['class' => $class->id]))->assertRedirect();

    $this->post(route('administrators.classes.restore', ['class' => $class->id]))
        ->assertRedirect(route('administrators.classes.index'));

    $this->assertNotSoftDeleted('classes', ['id' => $class->id]);
    $this->assertNotSoftDeleted('schedule', ['id' => $schedule->id]);
    $this->assertNotSoftDeleted('class_enrollments', ['id' => $enrollment->id]);
});

it('lists archived classes when the deleted view is requested', function (): void {
    $admin = classArchiveAdmin();
    $this->actingAs($admin);

    $class = classArchiveClass();

    $this->delete(route('administrators.classes.destroy', ['class' => $class->id]))->assertRedirect();

    $this->get(route('administrators.classes.index', ['trashed' => 'only']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/classes/index', false)
            ->has('classes.data', 1)
            ->where('classes.data.0.id', $class->id)
        );
});

it('permanently removes a class when it is force deleted', function (): void {
    $admin = classArchiveAdmin();
    $this->actingAs($admin);

    $class = classArchiveClass();

    $this->delete(route('administrators.classes.destroy', ['class' => $class->id]))->assertRedirect();

    $this->delete(route('administrators.classes.force-destroy', ['class' => $class->id]))
        ->assertRedirect(route('administrators.classes.index'));

    $this->assertDatabaseMissing('classes', ['id' => $class->id]);
});

it('refuses to delete a class without the delete permission', function (): void {
    // The librarian can reach the administrator portal but may not manage classes.
    $support = User::factory()->create(['role' => UserRole::Librarian]);

    $this->actingAs($support);

    $class = classArchiveClass();

    $this->delete(route('administrators.classes.destroy', ['class' => $class->id]))->assertForbidden();

    $this->assertDatabaseHas('classes', ['id' => $class->id, 'deleted_at' => null]);
});

it('allows a user who holds the delete permission to archive a class', function (): void {
    $permission = Permission::findOrCreate('Delete:Classes', 'web');

    $registrar = User::factory()->create(['role' => UserRole::Librarian]);
    $registrar->givePermissionTo($permission);

    $this->actingAs($registrar);

    $class = classArchiveClass();

    $this->delete(route('administrators.classes.destroy', ['class' => $class->id]))->assertRedirect();

    $this->assertSoftDeleted('classes', ['id' => $class->id]);
});

it('refuses to create a class without the create permission', function (): void {
    $support = User::factory()->create(['role' => UserRole::Librarian]);

    $this->actingAs($support);

    $this->post(route('administrators.classes.store'), [
        'classification' => 'college',
        'course_codes' => [Course::factory()->create()->id],
        'subject_ids' => [],
        'subject_code' => 'TEST 1',
        'academic_year' => 1,
        'faculty_id' => null,
        'semester' => 1,
        'school_year' => '2026 - 2027',
        'section' => 'A',
        'room_id' => 1,
        'maximum_slots' => 40,
        'schedules' => [],
    ])->assertForbidden();
});

it('reports the class management capabilities of the signed in user', function (): void {
    $admin = classArchiveAdmin();
    $this->actingAs($admin);

    $this->get(route('administrators.classes.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/classes/index', false)
            ->where('capabilities.create', true)
            ->where('capabilities.update', true)
            ->where('capabilities.delete', true)
            ->where('capabilities.restore', true)
        );
});
