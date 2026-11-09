<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Services\UserSystemRoleService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Permission::findOrCreate('ViewAny:User', 'web');
    Permission::findOrCreate('Update:User', 'web');
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
    Role::findOrCreate('instructor', 'web');
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

it('excludes users_with_roles from roles index when actor lacks ViewAny:User', function (): void {
    $actor = User::factory()->create(['role' => UserRole::Admin]);
    $actor->givePermissionTo('ViewAny:Role');

    User::factory()->create(['role' => UserRole::Student]);

    $response = $this->actingAs($actor)
        ->get(portalUrlForAdministrators('/administrators/roles'))
        ->assertOk();

    expect($response->inertiaProps('users_with_roles'))->toBe([]);
});

it('includes users_with_roles in roles index when actor has ViewAny:User', function (): void {
    $actor = User::factory()->create(['role' => UserRole::Admin]);
    $actor->givePermissionTo(['ViewAny:Role', 'ViewAny:User']);

    User::factory()->create(['role' => UserRole::Student]);

    $response = $this->actingAs($actor)
        ->get(portalUrlForAdministrators('/administrators/roles'))
        ->assertOk();

    expect($response->inertiaProps('users_with_roles'))->not->toBeEmpty();
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

it('blocks self-assignment of a manageable role', function (): void {
    $actor = User::factory()->create(['role' => UserRole::Admin]);
    $actor->givePermissionTo('Assign:Role');

    $this->actingAs($actor)
        ->post(portalUrlForAdministrators('/administrators/roles/assign'), [
            'user_id' => $actor->id,
            'role_name' => 'dean',
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

it('allows authorized administrator to assign shs_student role', function (): void {
    Role::findOrCreate('shs_student', 'web');

    $actor = User::factory()->create(['role' => UserRole::Admin]);
    $actor->givePermissionTo('Assign:Role');

    $target = User::factory()->create(['role' => UserRole::Student]);

    $this->actingAs($actor)
        ->post(portalUrlForAdministrators('/administrators/roles/assign'), [
            'user_id' => $target->id,
            'role_name' => 'shs_student',
        ])
        ->assertRedirect(route('administrators.roles.index'));

    expect($target->fresh()->role)->toBe(UserRole::ShsStudent);
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

it('preserves permissions when assigning a legacy role label variant', function (): void {
    $legacyRole = Role::findOrCreate('Dean', 'web');
    $legacyRole->givePermissionTo('some_privileged_perm');

    $actor = User::factory()->create(['role' => UserRole::Admin]);
    $actor->givePermissionTo(['Assign:Role', 'some_privileged_perm']);

    $target = User::factory()->create(['role' => UserRole::Student]);

    $this->actingAs($actor)
        ->post(portalUrlForAdministrators('/administrators/roles/assign'), [
            'user_id' => $target->id,
            'role_name' => 'Dean',
        ])
        ->assertRedirect(route('administrators.roles.index'));

    expect($target->fresh()->role)->toBe(UserRole::Dean);
    expect($target->fresh()->hasRole('Dean'))->toBeTrue();
    expect($target->fresh()->hasPermissionTo('some_privileged_perm'))->toBeTrue();
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

    expect(Role::where('name', 'Super Administrator')->exists())->toBeFalse();

    $this->actingAs($actor)
        ->post(portalUrlForAdministrators('/administrators/roles'), [
            'name' => 'Super Administrator',
        ])
        ->assertStatus(422);

    expect(Role::where('name', 'Super Administrator')->exists())->toBeFalse();
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

it('blocks renaming a custom role to a reserved system role name', function (): void {
    $actor = User::factory()->create(['role' => UserRole::Admin]);
    $actor->givePermissionTo('Update:Role');

    $customRole = Role::create(['name' => 'custom_support', 'guard_name' => 'web']);

    $this->actingAs($actor)
        ->put(portalUrlForAdministrators('/administrators/roles/'.$customRole->id), [
            'name' => 'Super Administrator',
        ])
        ->assertStatus(422);

    expect($customRole->fresh()->name)->toBe('custom_support');
});

it('blocks updating permissions of a system role actor cannot manage', function (): void {
    $actor = User::factory()->create(['role' => UserRole::Dean]);
    $actor->givePermissionTo('Update:Role');

    $adminRole = Role::where('name', 'admin')->firstOrFail();

    $this->actingAs($actor)
        ->put(portalUrlForAdministrators('/administrators/roles/'.$adminRole->id), [
            'name' => 'admin',
            'permissions' => [],
        ])
        ->assertForbidden();
});

it('blocks updating permissions of a role assigned to users of equal or higher authority', function (): void {
    $actor = User::factory()->create(['role' => UserRole::Dean]);
    $actor->givePermissionTo('Update:Role');

    $president = User::factory()->create(['role' => UserRole::President]);
    $customRole = Role::create(['name' => 'executive_aid', 'guard_name' => 'web']);
    $president->assignRole($customRole);

    $this->actingAs($actor)
        ->put(portalUrlForAdministrators('/administrators/roles/'.$customRole->id), [
            'name' => 'executive_aid',
            'permissions' => [],
        ])
        ->assertForbidden();
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

it('blocks changing roles in Filament user editor without Assign:Role permission', function (): void {
    Filament\Facades\Filament::setCurrentPanel('admin');

    $actor = User::factory()->create(['role' => UserRole::Dean]);
    $actor->givePermissionTo(['ViewAny:User', 'Update:User']);

    $target = User::factory()->create(['role' => UserRole::Student]);

    $this->actingAs($actor);

    Livewire\Livewire::test(App\Filament\Resources\Users\Pages\EditUser::class, ['record' => $target->getRouteKey()])
        ->set('data.role', UserRole::Instructor->value)
        ->set('data.feature_flags', [])
        ->call('save')
        ->assertStatus(403);

    expect($target->fresh()->role)->toBe(UserRole::Student);
});

it('blocks changing roles in Filament user editor for users of equal or higher authority', function (): void {
    Filament\Facades\Filament::setCurrentPanel('admin');

    $actor = User::factory()->create(['role' => UserRole::Dean]);
    $actor->givePermissionTo(['ViewAny:User', 'Update:User', 'Assign:Role']);

    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($actor);

    Livewire\Livewire::test(App\Filament\Resources\Users\Pages\EditUser::class, ['record' => $admin->getRouteKey()])
        ->set('data.role', UserRole::Instructor->value)
        ->set('data.feature_flags', [])
        ->call('save')
        ->assertStatus(403);

    expect($admin->fresh()->role)->toBe(UserRole::Admin);
});

it('allows updating profile in Filament user editor without Assign:Role when role is unchanged', function (): void {
    Filament\Facades\Filament::setCurrentPanel('admin');

    $actor = User::factory()->create(['role' => UserRole::Dean]);
    $actor->givePermissionTo(['ViewAny:User', 'Update:User']);

    $target = User::factory()->create(['role' => UserRole::Instructor]);

    $this->actingAs($actor);

    Livewire\Livewire::test(App\Filament\Resources\Users\Pages\EditUser::class, ['record' => $target->getRouteKey()])
        ->set('data.name', 'Updated Instructor Name')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($target->fresh()->name)->toBe('Updated Instructor Name');
    expect($target->fresh()->role)->toBe(UserRole::Instructor);
});
