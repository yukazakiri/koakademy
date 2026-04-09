<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function grantUserManagementPermission(User $user): void
{
    Permission::findOrCreate('ViewAny:User', 'web');

    $role = Role::findOrCreate($user->role->value, 'web');
    $role->syncPermissions(['ViewAny:User']);
}

it('shows online users count from redis session tracking', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-01-17 13:30:00'));

    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantUserManagementPermission($admin);

    Config::set('session.driver', 'redis');

    $key = config('cache.prefix', '').'online-users';
    Redis::del($key);
    Redis::zadd($key, [
        (string) $admin->id => now()->timestamp,
    ]);

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators('/administrators/users'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/users/index', false)
            ->has('analytics', fn (AssertableInertia $analytics): AssertableInertia => $analytics
                ->where('online_users', 1)
                ->etc()
            )
        );
});

it('forbids registrar users from accessing user management without permission', function (): void {
    $registrar = User::factory()->create([
        'role' => UserRole::Registrar,
    ]);

    $this->actingAs($registrar)
        ->get(portalUrlForAdministrators('/administrators/users'))
        ->assertForbidden();
});
