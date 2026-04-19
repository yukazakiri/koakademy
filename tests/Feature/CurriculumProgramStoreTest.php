<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Department;
use App\Models\User;


it('allows an administrator to store a new curriculum program', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin->value,
    ]);

    $department = Department::factory()->create();

    $payload = [
        'code' => 'BSE',
        'title' => 'Bachelor of Secondary Education',
        'description' => 'Test description',
        'department_id' => $department->id,
        'lec_per_unit' => 500,
        'lab_per_unit' => 600,
        'remarks' => 'Test remarks',
        'curriculum_year' => '2024-2025',
        'miscelaneous' => 3500,
    ];

    $response = $this->actingAs($admin)
        ->post(route('administrators.curriculum.programs.store'), $payload);

    $response->assertSessionHas('success', 'Program created successfully.');
    $response->assertRedirect();

    $this->assertDatabaseHas('courses', [
        'code' => 'BSE',
        'title' => 'Bachelor of Secondary Education',
        'department_id' => $department->id,
        'is_active' => true,
        'lec_per_unit' => 500,
        'lab_per_unit' => 600,
        'miscelaneous' => 3500,
    ]);
});

it('prevents non-administrators from creating a curriculum program', function () {
    $student = User::factory()->create([
        'role' => UserRole::Student->value,
    ]);

    $department = Department::factory()->create();

    $payload = [
        'code' => 'BSE',
        'title' => 'Bachelor of Secondary Education',
        'department_id' => $department->id,
    ];

    $response = $this->actingAs($student)
        ->post(route('administrators.curriculum.programs.store'), $payload);

    $response->assertForbidden();

    $this->assertDatabaseMissing('courses', [
        'code' => 'BSE',
    ]);
});

it('validates required fields when creating a curriculum program', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin->value,
    ]);

    $response = $this->actingAs($admin)
        ->post(route('administrators.curriculum.programs.store'), []);

    $response->assertSessionHasErrors([
        'code',
        'title',
        'department_id',
    ]);
});
