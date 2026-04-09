<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

it('shares permissions from the matching spatie role for admin sidebar access', function (): void {
    $permissionNames = [
        'ViewAny:Student',
        'ViewAny:StudentEnrollment',
    ];

    foreach ($permissionNames as $permissionName) {
        Permission::firstOrCreate([
            'name' => $permissionName,
            'guard_name' => 'web',
        ]);
    }

    $registrarRole = Role::firstOrCreate([
        'name' => UserRole::Registrar->value,
        'guard_name' => 'web',
    ]);

    $registrarRole->syncPermissions($permissionNames);

    $user = User::factory()->create([
        'role' => UserRole::Registrar,
    ]);

    actingAs($user);

    $request = Request::create('/administrators/dashboard');
    $request->setUserResolver(static fn (): User => $user);

    $shared = app(HandleInertiaRequests::class)->share($request);

    expect($shared['auth']['user']['permissions'])->toEqualCanonicalizing($permissionNames);
});

it('shares permissions from legacy display-name spatie roles for admin sidebar access', function (): void {
    $permissionNames = [
        'ViewAny:Student',
        'ViewAny:StudentEnrollment',
    ];

    foreach ($permissionNames as $permissionName) {
        Permission::firstOrCreate([
            'name' => $permissionName,
            'guard_name' => 'web',
        ]);
    }

    $registrarRole = Role::firstOrCreate([
        'name' => UserRole::Registrar->getLabel(),
        'guard_name' => 'web',
    ]);

    $registrarRole->syncPermissions($permissionNames);

    $user = User::factory()->create([
        'role' => UserRole::Registrar,
    ]);

    actingAs($user);

    $request = Request::create('/administrators/dashboard');
    $request->setUserResolver(static fn (): User => $user);

    $shared = app(HandleInertiaRequests::class)->share($request);

    expect($shared['auth']['user']['permissions'])->toEqualCanonicalizing($permissionNames);
});

it('prefers the canonical enum role over a legacy display-name role when both spatie roles exist', function (): void {
    Permission::firstOrCreate([
        'name' => 'ViewAny:Student',
        'guard_name' => 'web',
    ]);

    Permission::firstOrCreate([
        'name' => 'ViewAny:User',
        'guard_name' => 'web',
    ]);

    $canonicalRegistrarRole = Role::firstOrCreate([
        'name' => UserRole::Registrar->value,
        'guard_name' => 'web',
    ]);
    $canonicalRegistrarRole->syncPermissions(['ViewAny:Student']);

    $legacyRegistrarRole = Role::firstOrCreate([
        'name' => UserRole::Registrar->getLabel(),
        'guard_name' => 'web',
    ]);
    $legacyRegistrarRole->syncPermissions(['ViewAny:User']);

    $user = User::factory()->create([
        'role' => UserRole::Registrar,
    ]);

    actingAs($user);

    $request = Request::create('/administrators/dashboard');
    $request->setUserResolver(static fn (): User => $user);

    $shared = app(HandleInertiaRequests::class)->share($request);

    expect($shared['auth']['user']['permissions'])->toEqual(['ViewAny:Student']);
});

it('does not grant registrar finance or user-management permissions through the seeded role map', function (): void {
    $this->seed(RolesSeeder::class);

    $registrarRole = Role::findByName(UserRole::Registrar->value, 'web');
    $permissionNames = $registrarRole->permissions->pluck('name');

    expect($permissionNames->contains('ViewAny:Student'))->toBeTrue()
        ->and($permissionNames->contains('ViewAny:StudentEnrollment'))->toBeTrue()
        ->and($permissionNames->contains('ViewAny:User'))->toBeFalse()
        ->and($permissionNames->contains('View:Cashier'))->toBeFalse();
});
