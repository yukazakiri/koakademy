<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

function classRecordAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

function classRecord(): Classes
{
    $currentYear = date('Y');

    return Classes::factory()->create([
        'school_year' => $currentYear.' - '.($currentYear + 1),
        'semester' => 1,
        'classification' => 'college',
    ]);
}

it('reports the class management capabilities on the class record', function (): void {
    $admin = classRecordAdmin();
    $this->actingAs($admin);

    $class = classRecord();

    $this->get(route('administrators.classes.show', ['class' => $class->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/classes/show', false)
            ->where('class.id', $class->id)
            ->where('class.is_archived', false)
            ->where('capabilities.update', true)
            ->where('capabilities.delete', true)
            ->where('capabilities.restore', true)
        );
});

it('marks an archived class on the class record', function (): void {
    $admin = classRecordAdmin();
    $this->actingAs($admin);

    $class = classRecord();
    $class->delete();

    $this->get(route('administrators.classes.show', ['class' => $class->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/classes/show', false)
            ->where('class.id', $class->id)
            ->where('class.is_archived', true)
            ->where('class.deleted_at', fn ($value): bool => is_string($value) && $value !== '')
        );
});

it('withholds the class management capabilities from a user without the permissions', function (): void {
    $librarian = User::factory()->create(['role' => UserRole::Librarian]);
    $this->actingAs($librarian);

    $class = classRecord();

    $this->get(route('administrators.classes.show', ['class' => $class->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/classes/show', false)
            ->where('capabilities.update', false)
            ->where('capabilities.delete', false)
        );
});

it('reports the work queues and the class type counts on the class list', function (): void {
    $admin = classRecordAdmin();
    $this->actingAs($admin);

    $currentYear = date('Y');
    $schoolYear = $currentYear.' - '.($currentYear + 1);

    $course = Course::factory()->create(['code' => 'BSBA']);

    // A class that is complete.
    $complete = Classes::factory()->create([
        'school_year' => $schoolYear,
        'semester' => 1,
        'classification' => 'college',
        'course_codes' => [$course->id],
        'maximum_slots' => 10,
    ]);

    // A class with no faculty assigned.
    Classes::factory()->create([
        'school_year' => $schoolYear,
        'semester' => 1,
        'classification' => 'college',
        'course_codes' => [$course->id],
        'maximum_slots' => 10,
        'faculty_id' => null,
    ]);

    // A class with more students than seats.
    $overCapacity = Classes::factory()->create([
        'school_year' => $schoolYear,
        'semester' => 1,
        'classification' => 'college',
        'course_codes' => [$course->id],
        'maximum_slots' => 1,
    ]);

    // Two students in a class with one seat: over capacity, not merely full.
    foreach ([1, 2] as $ignored) {
        ClassEnrollment::factory()->create([
            'class_id' => $overCapacity->id,
            'student_id' => Student::factory()->create([
                'course_id' => $course->id,
                'document_location_id' => null,
            ])->id,
        ]);
    }

    ClassEnrollment::factory()->create([
        'class_id' => $complete->id,
        'student_id' => Student::factory()->create([
            'course_id' => $course->id,
            'document_location_id' => null,
        ])->id,
    ]);

    $this->get(route('administrators.classes.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/classes/index', false)
            ->where('classes.meta.queues.unassigned_faculty', 1)
            ->where('classes.meta.queues.over_capacity', 1)
            ->where('classes.meta.classification_counts.college', 3)
            ->where('classes.meta.classification_counts.shs', 0)
        );
});

it('filters the class list down to the classes that need a faculty member', function (): void {
    $admin = classRecordAdmin();
    $this->actingAs($admin);

    $currentYear = date('Y');
    $schoolYear = $currentYear.' - '.($currentYear + 1);

    $unassigned = Classes::factory()->create([
        'school_year' => $schoolYear,
        'semester' => 1,
        'classification' => 'college',
        'faculty_id' => null,
    ]);

    Classes::factory()->create([
        'school_year' => $schoolYear,
        'semester' => 1,
        'classification' => 'college',
    ]);

    $this->get(route('administrators.classes.index', ['needs_faculty' => 1]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/classes/index', false)
            ->has('classes.data', 1)
            ->where('classes.data.0.id', $unassigned->id)
        );
});

it('refuses to update a class without the update permission', function (): void {
    $librarian = User::factory()->create(['role' => UserRole::Librarian]);
    $this->actingAs($librarian);

    $class = classRecord();

    $this->patch(route('administrators.classes.update', ['class' => $class->id]), [
        'section' => 'B',
    ])->assertForbidden();

    $this->assertDatabaseHas('classes', ['id' => $class->id, 'section' => $class->section]);
});

it('lets a user with the update permission change a class', function (): void {
    $permission = Permission::findOrCreate('Update:Classes', 'web');

    $librarian = User::factory()->create(['role' => UserRole::Librarian]);
    $librarian->givePermissionTo($permission);

    $this->actingAs($librarian);

    $class = classRecord();

    $this->patch(route('administrators.classes.update', ['class' => $class->id]), [
        'section' => 'B',
    ])->assertRedirect(route('administrators.classes.index'));

    $this->assertDatabaseHas('classes', ['id' => $class->id, 'section' => 'B']);
});
