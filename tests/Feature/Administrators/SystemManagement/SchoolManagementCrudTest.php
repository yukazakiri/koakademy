<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\School;
use App\Models\User;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

function grantSchoolManagementPermission(User $user): void
{
    foreach (['View:SystemManagementSchool', 'Update:SystemManagementSchool'] as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $user->givePermissionTo(['View:SystemManagementSchool', 'Update:SystemManagementSchool']);
}

it('updates any school record from system management', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => null,
    ]);
    grantSchoolManagementPermission($admin);
    $school = School::factory()->create();

    actingAs($admin)
        ->put(route('administrators.system-management.schools.update', $school), [
            'name' => 'Updated School Name',
            'code' => 'UPD01',
            'description' => 'Updated description',
            'location' => 'Updated location',
            'phone' => '+63 900 000 0000',
            'email' => 'school-updated@example.com',
            'dean_name' => 'Dean Updated',
            'dean_email' => 'dean.updated@example.com',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $school->refresh();

    expect($school->name)->toBe('Updated School Name')
        ->and($school->code)->toBe('UPD01')
        ->and($school->dean_name)->toBe('Dean Updated')
        ->and($school->dean_email)->toBe('dean.updated@example.com');
});

it('toggles school active status', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => null,
    ]);
    grantSchoolManagementPermission($admin);
    $school = School::factory()->create([
        'is_active' => true,
    ]);

    actingAs($admin)
        ->patch(route('administrators.system-management.schools.status.update', $school), [
            'is_active' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $school->refresh();

    expect($school->is_active)->toBeFalse();
});

it('prevents deleting the last school', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => null,
    ]);
    grantSchoolManagementPermission($admin);
    $school = School::factory()->create();
    School::query()->where('id', '!=', $school->id)->delete();

    actingAs($admin)
        ->delete(route('administrators.system-management.schools.destroy', $school))
        ->assertRedirect()
        ->assertSessionHasErrors('school');

    expect(School::query()->whereKey($school->id)->exists())->toBeTrue();
});

it('soft deletes a school without purging related school-scoped records', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => null,
    ]);
    grantSchoolManagementPermission($admin);

    $replacementSchool = School::factory()->create();
    $schoolToDelete = School::factory()->create();

    $user = User::factory()->create([
        'role' => UserRole::Instructor,
        'school_id' => $schoolToDelete->id,
        'faculty_id_number' => 'FAC-999',
    ]);

    $department = Department::factory()->create([
        'school_id' => $schoolToDelete->id,
    ]);

    actingAs($admin)
        ->delete(route('administrators.system-management.schools.destroy', $schoolToDelete))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(School::query()->whereKey($schoolToDelete->id)->exists())->toBeFalse()
        ->and(School::withTrashed()->whereKey($schoolToDelete->id)->exists())->toBeTrue()
        ->and(School::query()->whereKey($replacementSchool->id)->exists())->toBeTrue()
        ->and(Department::query()->whereKey($department->id)->exists())->toBeTrue();

    $user->refresh();
    expect($user->school_id)->toBe($schoolToDelete->id);
});

it('force deletes a school and purges related school-scoped records', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => null,
    ]);
    grantSchoolManagementPermission($admin);

    $replacementSchool = School::factory()->create();
    $schoolToDelete = School::factory()->create();

    $user = User::factory()->create([
        'role' => UserRole::Instructor,
        'school_id' => $schoolToDelete->id,
        'faculty_id_number' => 'FAC-123',
    ]);

    $department = Department::factory()->create([
        'school_id' => $schoolToDelete->id,
    ]);

    actingAs($admin)
        ->delete(route('administrators.system-management.schools.force-destroy', $schoolToDelete->id))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(School::withTrashed()->whereKey($schoolToDelete->id)->exists())->toBeFalse()
        ->and(School::query()->whereKey($replacementSchool->id)->exists())->toBeTrue()
        ->and(Department::query()->whereKey($department->id)->exists())->toBeFalse();

    $user->refresh();
    expect($user->school_id)
        ->not->toBe($schoolToDelete->id)
        ->and(School::query()->whereKey($user->school_id)->exists())->toBeTrue();
});

it('deletes an unused non-active school', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => null,
    ]);
    grantSchoolManagementPermission($admin);

    $activeSchool = School::factory()->create([
        'is_active' => true,
    ]);
    $schoolToDelete = School::factory()->create([
        'is_active' => false,
    ]);

    actingAs($admin)
        ->delete(route('administrators.system-management.schools.destroy', $schoolToDelete))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(School::query()->whereKey($activeSchool->id)->exists())->toBeTrue()
        ->and(School::query()->whereKey($schoolToDelete->id)->exists())->toBeFalse()
        ->and(School::withTrashed()->whereKey($schoolToDelete->id)->exists())->toBeTrue();
});
