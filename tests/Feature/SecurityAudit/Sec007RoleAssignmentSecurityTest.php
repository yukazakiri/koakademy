<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Services\UserSystemRoleService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Permission::findOrCreate('ViewAny:User', 'web');
    Permission::findOrCreate('ViewAny:Role', 'web');
    Permission::findOrCreate('View:Role', 'web');
    Permission::findOrCreate('Create:Role', 'web');
    Permission::findOrCreate('Update:Role', 'web');
    Permission::findOrCreate('Delete:Role', 'web');
    Permission::findOrCreate('Assign:Role', 'web');
    Permission::findOrCreate('Create:Permission', 'web');
    Permission::findOrCreate('Delete:Permission', 'web');
    Permission::findOrCreate('some_privileged_perm', 'web');

    Role::findOrCreate('super_admin', 'web');
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('dean', 'web');
    Role::findOrCreate('student', 'web');
});

it('blocks ViewAny:User holders from accessing roles index', function (): void {
    $user = User::factory()->create(['role' => UserRole::Student]);
    $user->givePermissionTo('ViewAny:User');

    $this->actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/roles'))
        ->assertForbidden();
});

it('allows ViewAny:Role holders to view roles index', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $admin->givePermissionTo('ViewAny:Role');

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators('/administrators/roles'))
        ->assertOk();
});

it('blocks ViewAny:User holders from assigning roles', function (): void {
    $actor = User::factory()->create(['role' => UserRole::Student]);
    $actor->givePermissionTo('ViewAny:User');

    $target = User::factory()->create(['role' => UserRole::Student]);

    $this->actingAs($actor)
        ->post(portalUrlForAdministrators('/administrators/roles/assign'), [
            'user_id' => $target->id,
            'role_name' => 'super_admin',
        ])
        ->assertForbidden();

    expect($target->fresh()->role)->toBe(UserRole::Student);
});

it('blocks ViewAny:User holders from creating roles', function (): void {
    $actor = User::factory()->create(['role' => UserRole::Student]);
    $actor->givePermissionTo('ViewAny:User');

    $this->actingAs($actor)
        ->post(portalUrlForAdministrators('/administrators/roles'), [
            'name' => 'attacker_role',
            'permissions' => ['ViewAny:User'],
        ])
        ->assertForbidden();

    expect(Role::where('name', 'attacker_role')->exists())->toBeFalse();
});

it('blocks ViewAny:User holders from updating roles', function (): void {
    $actor = User::factory()->create(['role' => UserRole::Student]);
    $actor->givePermissionTo('ViewAny:User');

    $customRole = Role::create(['name' => 'test_role', 'guard_name' => 'web']);

    $this->actingAs($actor)
        ->put(portalUrlForAdministrators('/administrators/roles/'.$customRole->id), [
            'name' => 'renamed_role',
        ])
        ->assertForbidden();
});

it('blocks ViewAny:User holders from deleting roles', function (): void {
    $actor = User::factory()->create(['role' => UserRole::Student]);
    $actor->givePermissionTo('ViewAny:User');

    $customRole = Role::create(['name' => 'test_role_delete', 'guard_name' => 'web']);

    $this->actingAs($actor)
        ->delete(portalUrlForAdministrators('/administrators/roles/'.$customRole->id))
        ->assertForbidden();

    expect(Role::where('name', 'test_role_delete')->exists())->toBeTrue();
});

it('blocks ViewAny:User holders from creating permissions', function (): void {
    $actor = User::factory()->create(['role' => UserRole::Student]);
    $actor->givePermissionTo('ViewAny:User');

    $this->actingAs($actor)
        ->post(portalUrlForAdministrators('/administrators/permissions'), [
            'name' => 'malicious_permission',
        ])
        ->assertForbidden();

    expect(Permission::where('name', 'malicious_permission')->exists())->toBeFalse();
});

it('blocks ViewAny:User holders from deleting permissions', function (): void {
    $actor = User::factory()->create(['role' => UserRole::Student]);
    $actor->givePermissionTo('ViewAny:User');

    $perm = Permission::create(['name' => 'test_delete_permission', 'guard_name' => 'web']);

    $this->actingAs($actor)
        ->delete(portalUrlForAdministrators('/administrators/permissions/'.$perm->id))
        ->assertForbidden();

    expect(Permission::where('name', 'test_delete_permission')->exists())->toBeTrue();
});

it('blocks self-assignment of roles even with Assign:Role permission', function (): void {
    $actor = User::factory()->create(['role' => UserRole::Admin]);
    $actor->givePermissionTo('Assign:Role');

    $this->actingAs($actor)
        ->post(portalUrlForAdministrators('/administrators/roles/assign'), [
            'user_id' => $actor->id,
            'role_name' => 'super_admin',
        ])
        ->assertForbidden();

    expect($actor->fresh()->role)->toBe(UserRole::Admin);
});

it('blocks assigning super_admin when actor cannot manage super_admin', function (): void {
    $actor = User::factory()->create(['role' => UserRole::Admin]);
    $actor->givePermissionTo('Assign:Role');

    $target = User::factory()->create(['role' => UserRole::Student]);

    $this->actingAs($actor)
        ->post(portalUrlForAdministrators('/administrators/roles/assign'), [
            'user_id' => $target->id,
            'role_name' => 'super_admin',
        ])
        ->assertForbidden();

    expect($target->fresh()->role)->toBe(UserRole::Student);
});

it('blocks role assignment when actor lacks higher authority than target', function (): void {
    $actor = User::factory()->create(['role' => UserRole::Dean]);
    $actor->givePermissionTo('Assign:Role');

    $target = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($actor)
        ->post(portalUrlForAdministrators('/administrators/roles/assign'), [
            'user_id' => $target->id,
            'role_name' => 'student',
        ])
        ->assertForbidden();
});

it('blocks role assignment of custom roles containing unheld permissions', function (): void {
    $actor = User::factory()->create(['role' => UserRole::Admin]);
    $actor->givePermissionTo('Assign:Role');

    $customRole = Role::create(['name' => 'special_role', 'guard_name' => 'web']);
    $customRole->givePermissionTo('some_privileged_perm');

    $target = User::factory()->create(['role' => UserRole::Student]);

    $this->actingAs($actor)
        ->post(portalUrlForAdministrators('/administrators/roles/assign'), [
            'user_id' => $target->id,
            'role_name' => 'special_role',
        ])
        ->assertForbidden();
});

it('allows assigning a manageable system role to a subordinate user', function (): void {
    $actor = User::factory()->create(['role' => UserRole::Admin]);
    $actor->givePermissionTo('Assign:Role');

    $target = User::factory()->create(['role' => UserRole::Student]);

    $this->actingAs($actor)
        ->post(portalUrlForAdministrators('/administrators/roles/assign'), [
            'user_id' => $target->id,
            'role_name' => 'dean',
        ])
        ->assertRedirect(route('administrators.roles.index'));

    expect($target->fresh()->role)->toBe(UserRole::Dean);
    expect(app(UserSystemRoleService::class)->assigned($target->fresh()))
        ->toContain(UserRole::Dean);
});

it('allows assigning a custom role when actor holds all its permissions', function (): void {
    $actor = User::factory()->create(['role' => UserRole::Admin]);
    $actor->givePermissionTo(['Assign:Role', 'some_privileged_perm']);

    $customRole = Role::create(['name' => 'allowed_custom_role', 'guard_name' => 'web']);
    $customRole->givePermissionTo('some_privileged_perm');

    $target = User::factory()->create(['role' => UserRole::Student]);

    $this->actingAs($actor)
        ->post(portalUrlForAdministrators('/administrators/roles/assign'), [
            'user_id' => $target->id,
            'role_name' => 'allowed_custom_role',
        ])
        ->assertRedirect(route('administrators.roles.index'));

    expect($target->fresh()->hasRole('allowed_custom_role'))->toBeTrue();
    expect($target->fresh()->role)->toBe(UserRole::Student);
});

it('blocks creating a role with unheld permissions', function (): void {
    $actor = User::factory()->create(['role' => UserRole::Admin]);
    $actor->givePermissionTo('Create:Role');

    $this->actingAs($actor)
        ->post(portalUrlForAdministrators('/administrators/roles'), [
            'name' => 'new_priv_role',
            'permissions' => ['some_privileged_perm'],
        ])
        ->assertForbidden();
});

it('blocks creating a role with a reserved system role name', function (): void {
    $actor = User::factory()->create(['role' => UserRole::Admin]);
    $actor->givePermissionTo('Create:Role');

    $this->actingAs($actor)
        ->post(portalUrlForAdministrators('/administrators/roles'), [
            'name' => 'super_admin',
        ])
        ->assertStatus(302)
        ->assertSessionHasErrors(['name']);
});

it('blocks renaming a system role', function (): void {
    $actor = User::factory()->create(['role' => UserRole::Admin]);
    $actor->givePermissionTo('Update:Role');

    $superAdminRole = Role::where('name', 'super_admin')->firstOrFail();

    $this->actingAs($actor)
        ->put(portalUrlForAdministrators('/administrators/roles/'.$superAdminRole->id), [
            'name' => 'renamed_super_admin',
        ])
        ->assertForbidden();

    expect($superAdminRole->fresh()->name)->toBe('super_admin');
});

it('blocks deleting a system role', function (): void {
    $actor = User::factory()->create(['role' => UserRole::Admin]);
    $actor->givePermissionTo('Delete:Role');

    $superAdminRole = Role::where('name', 'super_admin')->firstOrFail();

    $this->actingAs($actor)
        ->delete(portalUrlForAdministrators('/administrators/roles/'.$superAdminRole->id))
        ->assertForbidden();

    expect(Role::where('name', 'super_admin')->exists())->toBeTrue();
});

it('allows creating and deleting permissions with proper authorization', function (): void {
    $creator = User::factory()->create(['role' => UserRole::Admin]);
    $creator->givePermissionTo(['Create:Permission', 'Delete:Permission']);

    $this->actingAs($creator)
        ->post(portalUrlForAdministrators('/administrators/permissions'), [
            'name' => 'new_test_perm',
        ])
        ->assertRedirect(route('administrators.roles.index'));

    $perm = Permission::where('name', 'new_test_perm')->firstOrFail();

    $this->actingAs($creator)
        ->delete(portalUrlForAdministrators('/administrators/permissions/'.$perm->id))
        ->assertRedirect(route('administrators.roles.index'));

    expect(Permission::where('name', 'new_test_perm')->exists())->toBeFalse();
});
