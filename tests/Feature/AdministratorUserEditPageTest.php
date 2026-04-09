<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\School;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('renders the administrator user edit page with beginner-friendly editing data', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    Permission::findOrCreate('ViewAny:User', 'web');
    Role::findOrCreate(UserRole::Admin->value, 'web')->syncPermissions(['ViewAny:User']);

    $school = School::factory()->create([
        'name' => 'School of Computing',
    ]);

    $department = Department::factory()->create([
        'name' => 'Software Engineering',
        'school_id' => $school->id,
    ]);

    $accessBundle = Role::findOrCreate('manage-student-records', 'web');

    $targetUser = User::factory()->create([
        'name' => 'Jamie Rivera',
        'role' => UserRole::Instructor,
        'school_id' => $school->id,
        'department_id' => $department->id,
    ]);

    $targetUser->assignRole($accessBundle);

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators("/administrators/users/{$targetUser->id}/edit"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/users/edit', false)
            ->where('user.name', 'Jamie Rivera')
            ->where('user.school_id', $school->id)
            ->where('user.department_id', $department->id)
            ->has('roles')
            ->has('schools', 1)
            ->has('departments', 1)
            ->has('permissions')
            ->has('auth_user'));
});
