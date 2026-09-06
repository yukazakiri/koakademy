<?php

declare(strict_types=1);

use App\Enums\SchoolLevel;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\School;
use App\Models\User;
use App\Models\UserSetting;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(Tests\TestCase::class);

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
            'school_level' => SchoolLevel::HigherEducation->value,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $school->refresh();

    expect($school->name)->toBe('Updated School Name')
        ->and($school->code)->toBe('UPD01')
        ->and($school->dean_name)->toBe('Dean Updated')
        ->and($school->dean_email)->toBe('dean.updated@example.com');
});

it('rejects invalid country codes when storing a school', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => null,
    ]);
    grantSchoolManagementPermission($admin);

    actingAs($admin)
        ->post(route('administrators.system-management.school.store'), [
            'name' => 'New School',
            'code' => 'NEW01',
            'country_code' => 'ZZ',
            'school_level' => SchoolLevel::HigherEducation->value,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('country_code');

    expect(School::query()->count())->toBe(0);
});

it('rejects invalid country codes on direct school detail updates', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => null,
    ]);
    grantSchoolManagementPermission($admin);
    $school = School::factory()->create([
        'country_code' => 'PH',
    ]);

    actingAs($admin)
        ->put(route('administrators.system-management.school-details.update'), [
            'school_id' => $school->id,
            'name' => 'Updated School Name',
            'code' => 'UPD01',
            'country_code' => 'ZZ',
            'school_level' => SchoolLevel::HigherEducation->value,
            'description' => 'Updated description',
            'location' => 'Updated location',
            'phone' => '+63 900 000 0000',
            'email' => 'school-updated@example.com',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('country_code');

    expect($school->refresh()->country_code)->toBe('PH');
});

it('rejects invalid country codes on managed school updates', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => null,
    ]);
    grantSchoolManagementPermission($admin);
    $school = School::factory()->create([
        'country_code' => 'PH',
    ]);

    actingAs($admin)
        ->put(route('administrators.system-management.schools.update', $school), [
            'name' => 'Updated School Name',
            'code' => 'UPD01',
            'country_code' => 'ZZ',
            'school_level' => SchoolLevel::HigherEducation->value,
            'description' => 'Updated description',
            'location' => 'Updated location',
            'phone' => '+63 900 000 0000',
            'email' => 'school-updated@example.com',
            'dean_name' => 'Dean Updated',
            'dean_email' => 'dean.updated@example.com',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('country_code');

    expect($school->refresh()->country_code)->toBe('PH');
});

it('normalizes trimmed lowercase country codes when storing a school', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => null,
    ]);
    grantSchoolManagementPermission($admin);

    actingAs($admin)
        ->post(route('administrators.system-management.school.store'), [
            'name' => 'New School',
            'code' => 'NEW01',
            'country_code' => ' us ',
            'school_level' => SchoolLevel::HigherEducation->value,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(School::query()->sole()->country_code)->toBe('US');
});

it('normalizes trimmed lowercase country codes on direct school detail updates and allows clearing them', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => null,
    ]);
    grantSchoolManagementPermission($admin);
    $school = School::factory()->create([
        'country_code' => 'PH',
    ]);

    actingAs($admin)
        ->put(route('administrators.system-management.school-details.update'), [
            'school_id' => $school->id,
            'name' => 'Updated School Name',
            'code' => 'UPD01',
            'country_code' => ' us ',
            'school_level' => SchoolLevel::HigherEducation->value,
            'description' => 'Updated description',
            'location' => 'Updated location',
            'phone' => '+63 900 000 0000',
            'email' => 'school-updated@example.com',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($school->refresh()->country_code)->toBe('US');

    actingAs($admin)
        ->put(route('administrators.system-management.school-details.update'), [
            'school_id' => $school->id,
            'name' => 'Updated School Name',
            'code' => 'UPD01',
            'country_code' => '   ',
            'school_level' => SchoolLevel::HigherEducation->value,
            'description' => 'Updated description',
            'location' => 'Updated location',
            'phone' => '+63 900 000 0000',
            'email' => 'school-updated@example.com',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($school->refresh()->country_code)->toBeNull();
});

it('allows clearing the managed school country code', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => null,
    ]);
    grantSchoolManagementPermission($admin);
    $school = School::factory()->create([
        'country_code' => 'PH',
    ]);

    actingAs($admin)
        ->put(route('administrators.system-management.schools.update', $school), [
            'name' => 'Updated School Name',
            'code' => 'UPD01',
            'country_code' => '   ',
            'school_level' => SchoolLevel::HigherEducation->value,
            'description' => 'Updated description',
            'location' => 'Updated location',
            'phone' => '+63 900 000 0000',
            'email' => 'school-updated@example.com',
            'dean_name' => 'Dean Updated',
            'dean_email' => 'dean.updated@example.com',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($school->refresh()->country_code)->toBeNull();
});

it('updates an institution school level from onboarding', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => null,
    ]);
    grantSchoolManagementPermission($admin);
    $school = School::factory()->create([
        'school_level' => null,
    ]);

    actingAs($admin)
        ->put(route('administrators.system-management.school-level.update'), [
            'school_id' => $school->id,
            'school_level' => SchoolLevel::SeniorHigh->value,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $school->refresh();

    expect($school->school_level)->toBe(SchoolLevel::SeniorHigh)
        ->and(UserSetting::query()->where('user_id', $admin->id)->value('active_school_id'))->toBe($school->id);
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
