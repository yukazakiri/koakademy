<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Http\Middleware\OnlineUserTrackingMiddleware;
use App\Models\User;
use App\Services\OnlineUserPresenceService;
use App\Services\UserSecurityActivityService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function userManagementAdmin(array $permissions): User
{
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $admin->givePermissionTo($permission);
    }

    return $admin;
}

it('shows only the selected accounts security events with safe overview fields', function (): void {
    $admin = userManagementAdmin(['View:User']);
    $target = User::factory()->create(['role' => UserRole::Student]);
    $other = User::factory()->create();
    $service = app(UserSecurityActivityService::class);
    $service->record($target, 'signed_in', 'success');
    $service->record($target, 'sign_in_failed', 'failure');
    $service->record($other, 'signed_in', 'success');

    $response = $this->actingAs($admin)->get(portalUrlForAdministrators('/administrators/users/'.$target->id.'?outcome=failure'));
    $response->assertOk();
    expect($response->inertiaProps('account.id'))->toBe($target->id)
        ->and($response->inertiaProps('account.last_login_at'))->toBeNull()
        ->and($response->inertiaProps('account.authenticator_configured'))->toBeFalse()
        ->and($response->inertiaProps('logs.total'))->toBe(1)
        ->and($response->inertiaProps('logs.data.0.event'))->toBe('sign_in_failed')
        ->and($response->inertiaProps('account'))->not->toHaveKeys(['password', 'app_authentication_secret', 'remember_token'])
        ->and($response->inertiaProps('logs.data.0'))->not->toHaveKeys(['ip_address', 'properties']);
});

it('paginates account history and filters by event', function (): void {
    $admin = userManagementAdmin(['View:User']);
    $target = User::factory()->create();
    for ($i = 0; $i < 23; $i++) {
        app(UserSecurityActivityService::class)->record($target, 'signed_in', 'success');
    }
    $response = $this->actingAs($admin)->get(portalUrlForAdministrators('/administrators/users/'.$target->id.'?event=signed_in&page=2'))->assertOk();
    expect($response->inertiaProps('logs.data'))->toHaveCount(3)
        ->and($response->inertiaProps('logs.total'))->toBe(23);
});

it('does not let directory viewers inspect or mutate accounts', function (string $method, string $suffix): void {
    $admin = userManagementAdmin(['ViewAny:User']);
    $target = User::factory()->create(['role' => UserRole::Student]);
    $this->actingAs($admin)->{$method}(portalUrlForAdministrators('/administrators/users/'.$target->id.$suffix))->assertForbidden();
})->with([['get', ''], ['get', '/edit'], ['delete', ''], ['post', '/impersonate'], ['post', '/reset-password'], ['put', '/verify-email']]);

it('blocks privileged role escalation even with update permission', function (): void {
    $admin = userManagementAdmin(['Update:User']);
    $target = User::factory()->create(['role' => UserRole::Student]);
    $this->actingAs($admin)->put(portalUrlForAdministrators('/administrators/users/'.$target->id), [
        'name' => $target->name, 'email' => $target->email, 'role' => UserRole::Developer->value,
    ])->assertForbidden();
    expect($target->fresh()->role)->toBe(UserRole::Student);
});

it('blocks permission role escalation through an otherwise valid update', function (): void {
    $admin = userManagementAdmin(['Update:User']);
    $target = User::factory()->create(['role' => UserRole::Student]);
    $role = Role::findOrCreate('super_admin', 'web');
    $this->actingAs($admin)->put(portalUrlForAdministrators('/administrators/users/'.$target->id), [
        'name' => $target->name, 'email' => $target->email, 'role' => UserRole::Student->value, 'roles' => [$role->id],
    ])->assertForbidden();
});

it('records completed web sign ins but not pending MFA or impersonation', function (bool $pending, bool $impersonating, bool $expected): void {
    $user = User::factory()->create();
    $request = Request::create('/login', 'POST');
    $request->setLaravelSession(app('session')->driver());
    if ($pending) {
        $request->session()->put('auth.2fa.id', $user->id);
    }
    if ($impersonating) {
        $request->session()->put('impersonator_id', $user->id + 1);
    }
    $this->app->instance('request', $request);
    $request->setUserResolver(fn () => $pending ? null : $user);
    event(new Login('web', $user, false));
    app(OnlineUserTrackingMiddleware::class)->handle($request, fn () => redirect('/dashboard'));
    expect($user->fresh()->last_login_at !== null)->toBe($expected)
        ->and(Activity::query()->inLog('user-security')->forEvent('signed_in')->count())->toBe($expected ? 1 : 0);
})->with([[false, false, true], [true, false, false], [false, true, false]]);

it('records known account failures without copying credentials', function (): void {
    $user = User::factory()->create();
    event(new Failed('web', $user, ['email' => $user->email, 'password' => 'secret-never-log']));
    $log = Activity::query()->inLog('user-security')->firstOrFail();
    expect($log->event)->toBe('sign_in_failed')
        ->and(json_encode($log->properties))->not->toContain('secret-never-log', $user->email);
});

it('records logout after the response without changing the last sign in', function (): void {
    $user = User::factory()->create();
    $request = Request::create('/logout', 'POST');
    $request->setLaravelSession(app('session')->driver());
    $this->app->instance('request', $request);
    $request->setUserResolver(fn () => null);
    event(new Logout('web', $user));
    app(OnlineUserTrackingMiddleware::class)->handle($request, fn () => redirect('/login'));
    expect(Activity::query()->inLog('user-security')->firstOrFail()->event)->toBe('signed_out')
        ->and($user->fresh()->last_login_at)->toBeNull();
});

it('persists last activity at most once per minute without changing account updated time', function (): void {
    $this->freezeTime();
    $user = User::factory()->create();
    $updatedAt = $user->updated_at->toISOString();
    $service = app(OnlineUserPresenceService::class);
    $service->recordActivity($user->id);
    $first = $user->fresh()->last_active_at->toISOString();
    $this->travel(30)->seconds();
    $service->recordActivity($user->id);
    expect($user->fresh()->last_active_at->toISOString())->toBe($first);
    $this->travel(31)->seconds();
    $service->recordActivity($user->id);
    expect($user->fresh()->last_active_at->toISOString())->not->toBe($first)
        ->and($user->fresh()->updated_at->toISOString())->toBe($updatedAt);
});

it('records a real password login only after MFA completes', function (): void {
    $provider = App\Filament\Auth\MultiFactor\SecurityAwareAppAuthentication::make();
    $secret = $provider->generateSecret();
    $user = User::factory()->create(['app_authentication_secret' => $secret]);
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));
    expect($user->fresh()->last_login_at)->toBeNull();
    $this->post('/two-factor-challenge', ['code' => 'invalid'])->assertSessionHasErrors('code');
    expect($user->fresh()->last_login_at)->toBeNull();
    $this->post('/two-factor-challenge', ['code' => $provider->getCurrentCode($user, $secret)])->assertRedirect();
    expect($user->fresh()->last_login_at)->not->toBeNull()
        ->and(Activity::query()->inLog('user-security')->forSubject($user)->forEvent('signed_in')->count())->toBe(1)
        ->and(Activity::query()->inLog('user-security')->forSubject($user)->forEvent('mfa_failed')->count())->toBe(1);
});

it('allows authorized edits of lower authority accounts and audits the actor', function (): void {
    $admin = userManagementAdmin(['Update:User']);
    $target = User::factory()->create(['role' => UserRole::Student]);
    $this->actingAs($admin)->put(portalUrlForAdministrators('/administrators/users/'.$target->id), [
        'name' => 'Updated student', 'email' => $target->email, 'role' => UserRole::Student->value,
    ])->assertRedirect();
    expect($target->fresh()->name)->toBe('Updated student')
        ->and(Activity::query()->inLog('user-security')->forSubject($target)->firstOrFail()->causer_id)->toBe($admin->id);
});

it('does not label an unconfigured MFA preference as configured protection', function (): void {
    $admin = userManagementAdmin(['ViewAny:User']);
    $target = User::factory()->create(['security_two_factor_enabled' => true]);
    $response = $this->actingAs($admin)->get(portalUrlForAdministrators('/administrators/users'))->assertOk();
    $row = collect($response->inertiaProps('users.data'))->firstWhere('id', $target->id);
    expect($row['mfa_configured'])->toBeFalse()
        ->and($row['capabilities']['update'])->toBeFalse();
});

it('records mobile token authentication without storing the issued token', function (): void {
    Illuminate\Support\Facades\Route::post('/test/mobile-login', [App\Http\Controllers\Api\V1\Auth\AuthTokenController::class, 'login']);
    $user = User::factory()->create(['role' => UserRole::Student, 'security_two_factor_enabled' => false]);
    $response = $this->postJson('/test/mobile-login', ['email' => $user->email, 'password' => 'password', 'device_name' => 'Test device'])->assertOk();
    expect($user->fresh()->last_login_at)->not->toBeNull()
        ->and($user->fresh()->last_active_at)->not->toBeNull();
    $log = Activity::query()->inLog('user-security')->forSubject($user)->firstOrFail();
    expect($log->event)->toBe('signed_in')
        ->and($log->getProperty('guard'))->toBe('sanctum')
        ->and(json_encode($log->properties))->not->toContain($response->json('data.token'));
});

it('records failed mobile passwords without updating last sign in', function (): void {
    Illuminate\Support\Facades\Route::post('/test/mobile-login', [App\Http\Controllers\Api\V1\Auth\AuthTokenController::class, 'login']);
    $user = User::factory()->create(['role' => UserRole::Student]);
    $this->postJson('/test/mobile-login', ['email' => $user->email, 'password' => 'incorrect', 'device_name' => 'Test device'])->assertUnauthorized();
    expect($user->fresh()->last_login_at)->toBeNull()
        ->and(Activity::query()->inLog('user-security')->forSubject($user)->firstOrFail()->event)->toBe('sign_in_failed');
});

it('suspends and reactivates lower authority accounts while revoking credentials', function (): void {
    $admin = userManagementAdmin(['Update:User']);
    $target = User::factory()->create(['role' => UserRole::Student]);
    $target->createToken('Device');
    $this->actingAs($admin)->put(portalUrlForAdministrators('/administrators/users/'.$target->id.'/access'), ['suspended' => true, 'reason' => 'Account review'])->assertRedirect();
    expect($target->fresh()->suspended_at)->not->toBeNull()
        ->and($target->tokens()->count())->toBe(0)
        ->and($target->fresh()->session_version)->toBe(1);
    $this->put(portalUrlForAdministrators('/administrators/users/'.$target->id.'/access'), ['suspended' => false, 'reason' => 'Review completed'])->assertRedirect();
    expect($target->fresh()->suspended_at)->toBeNull()
        ->and($target->fresh()->session_version)->toBe(2)
        ->and(Activity::query()->forSubject($target)->forEvent('account_reactivated')->firstOrFail()->getProperty('reason'))->toBe('Review completed');
});

it('blocks suspended password login', function (): void {
    $target = User::factory()->create();
    $target->forceFill(['suspended_at' => now()])->save();
    $this->post('/login', ['email' => $target->email, 'password' => 'password'])->assertForbidden();
    $this->assertGuest();
});

it('rejects suspended existing sessions and stale session versions', function (bool $suspended): void {
    $target = User::factory()->create();
    $target->forceFill(['suspended_at' => $suspended ? now() : null, 'session_version' => $suspended ? 0 : 1])->save();
    $this->actingAs($target)->get('/dashboard')->assertForbidden();
})->with([true, false]);

it('scopes individual session revocation and never exposes session IDs', function (): void {
    config(['session.driver' => 'database']);
    $admin = userManagementAdmin(['View:User', 'Update:User']);
    $target = User::factory()->create(['role' => UserRole::Student]);
    $other = User::factory()->create();
    foreach ([[$target, 'target-session-secret'], [$other, 'other-session-secret']] as [$owner, $id]) {
        Illuminate\Support\Facades\DB::table('sessions')->insert(['id' => $id, 'user_id' => $owner->id, 'payload' => '', 'last_activity' => now()->timestamp]);
    }
    $response = $this->actingAs($admin)->get(portalUrlForAdministrators('/administrators/users/'.$target->id))->assertOk();
    $reference = $response->inertiaProps('sessions.data.0.reference');
    expect($reference)->not->toBe('target-session-secret');
    $this->delete(portalUrlForAdministrators('/administrators/users/'.$target->id.'/sessions'), ['reference' => Illuminate\Support\Facades\Crypt::encryptString('other-session-secret')])->assertNotFound();
    $this->delete(portalUrlForAdministrators('/administrators/users/'.$target->id.'/sessions'), ['reference' => $reference])->assertRedirect();
    $this->assertDatabaseMissing('sessions', ['id' => 'target-session-secret']);
    $this->assertDatabaseHas('sessions', ['id' => 'other-session-secret']);
});

it('scopes API token revocation to the account', function (): void {
    $admin = userManagementAdmin(['Update:User']);
    $target = User::factory()->create(['role' => UserRole::Student]);
    $other = User::factory()->create();
    $token = $other->createToken('Other device')->accessToken;
    $this->actingAs($admin)->delete(portalUrlForAdministrators('/administrators/users/'.$target->id.'/tokens/'.$token->id))->assertNotFound();
    expect($other->tokens()->count())->toBe(1);
});

it('requires update permission and rejects self suspension', function (): void {
    $admin = userManagementAdmin(['View:User']);
    $target = User::factory()->create(['role' => UserRole::Student]);
    $this->actingAs($admin)->put(portalUrlForAdministrators('/administrators/users/'.$target->id.'/access'), ['suspended' => true, 'reason' => 'Review'])->assertForbidden();
    Permission::findOrCreate('Update:User', 'web');
    $admin->givePermissionTo('Update:User');
    $this->put(portalUrlForAdministrators('/administrators/users/'.$admin->id.'/access'), ['suspended' => true, 'reason' => 'Review'])->assertForbidden();
});

it('rejects suspended bearer token access after Sanctum authentication', function (): void {
    Illuminate\Support\Facades\Route::middleware(['api', 'auth:sanctum'])->get('/api/test/account-access', fn () => response()->json(['ok' => true]));
    $target = User::factory()->create();
    $token = $target->createToken('Test device')->plainTextToken;
    $target->forceFill(['suspended_at' => now()])->save();
    $this->withToken($token)->getJson('/api/test/account-access')->assertForbidden();
});

it('revoke all invalidates non database sessions until a fresh login', function (): void {
    config(['session.driver' => 'array']);
    $target = User::factory()->create(['security_two_factor_enabled' => false]);
    app(App\Services\AdministratorUserAccessService::class)->revokeAll($target);
    $this->actingAs($target)->get('/dashboard')->assertForbidden();
    $this->post('/login', ['email' => $target->email, 'password' => 'password'])->assertRedirect();
    $this->assertAuthenticatedAs($target);
    $this->get('/dashboard')->assertRedirect();
});

it('blocks a suspended account using a valid magic link', function (): void {
    $target = User::factory()->create();
    $target->forceFill(['suspended_at' => now()])->save();
    $token = Illuminate\Support\Str::random(40);
    Illuminate\Support\Facades\Cache::put("magic_link:{$target->id}:{$token}", true, now()->addMinutes(15));
    $url = Illuminate\Support\Facades\URL::temporarySignedRoute('magic-link.verify', now()->addMinutes(15), ['user' => $target->id, 'token' => $token]);
    $this->get($url)->assertForbidden();
    $this->assertGuest();
});

it('upgrades existing directory viewers with detail access but no editing powers', function (): void {
    $direct = userManagementAdmin(['ViewAny:User']);
    $roleViewer = User::factory()->create(['role' => UserRole::Admin]);
    $role = Role::findOrCreate('Legacy user viewer', 'web');
    $role->givePermissionTo('ViewAny:User');
    $roleViewer->assignRole($role);
    $target = User::factory()->create(['role' => UserRole::Student]);
    $migration = require database_path('migrations/2026_10_20_091245_grant_user_detail_view_to_existing_directory_viewers.php');
    $migration->up();
    $migration->up();
    foreach ([$direct, $roleViewer] as $viewer) {
        $viewer = $viewer->fresh();
        $response = $this->actingAs($viewer)->get(portalUrlForAdministrators('/administrators/users'))->assertOk();
        $account = collect($response->inertiaProps('users.data'))->firstWhere('id', $target->id);
        expect($account['capabilities']['view'])->toBeTrue()
            ->and($account['capabilities']['update'])->toBeFalse();
        $this->get(portalUrlForAdministrators('/administrators/users/'.$target->id))->assertOk();
        $this->get(portalUrlForAdministrators('/administrators/users/'.$target->id.'/edit'))->assertForbidden();
    }
});

it('allows a Spatie super administrator with a non administrative primary role to view user details', function (string $roleName): void {
    config(['filament-shield.super_admin.name' => $roleName]);
    $admin = User::factory()->create(['role' => UserRole::User]);
    $admin->assignRole(Role::findOrCreate($roleName, 'web'));
    $target = User::factory()->create(['role' => UserRole::Student]);

    $response = $this->actingAs($admin)->get(portalUrlForAdministrators('/administrators/users'))->assertOk();
    $account = collect($response->inertiaProps('users.data'))->firstWhere('id', $target->id);
    expect($account['capabilities']['view'])->toBeTrue();
    $this->get(portalUrlForAdministrators('/administrators/users/'.$target->id))->assertOk();
})->with(['super_admin', 'custom_super_admin']);

it('does not admit an ordinary user to administrator user details', function (): void {
    $ordinary = User::factory()->create(['role' => UserRole::User]);
    $target = User::factory()->create();
    $this->actingAs($ordinary)->get(portalUrlForAdministrators('/administrators/users/'.$target->id))->assertForbidden();
});

it('uses Spatie system role for hierarchy serialization and portal behavior', function (): void {
    $user = User::factory()->create(['role' => UserRole::User]);
    $user->assignRole(Role::findOrCreate('super_admin', 'web'));
    $target = User::factory()->create(['role' => UserRole::Admin]);
    expect($user->role)->toBe(UserRole::SuperAdmin)
        ->and($user->toArray()['role'])->toBe('super_admin')
        ->and($user->hasHigherAuthorityThan($target))->toBeTrue()
        ->and($user->canAccessAdminPortal())->toBeTrue();
});

it('changes one system role while preserving custom access', function (): void {
    $user = User::factory()->create(['role' => UserRole::Student]);
    $custom = Role::findOrCreate('Library support bundle', 'web');
    $user->assignRole(Role::findOrCreate('student', 'web'), $custom);
    app(App\Services\UserSystemRoleService::class)->assign($user, UserRole::Instructor);
    expect($user->fresh()->role)->toBe(UserRole::Instructor)
        ->and($user->fresh()->getRoleNames()->all())->toContain('instructor', 'Library support bundle')
        ->not->toContain('student')
        ->and($user->fresh()->getRawOriginal('role'))->toBe('instructor');
});

it('reports conflicting roles without changing grants', function (): void {
    $user = User::factory()->create(['role' => UserRole::User]);
    $user->assignRole(Role::findOrCreate('super_admin', 'web'), Role::findOrCreate('student', 'web'));
    $this->artisan('users:reconcile-system-roles')->expectsOutputToContain('multiple system roles')->assertFailed();
    expect($user->fresh()->getRoleNames()->all())->toContain('super_admin', 'student')
        ->and($user->fresh()->getRawOriginal('role'))->toBe('user');
});

it('reconciles unambiguous Spatie assignments and legacy only accounts explicitly', function (): void {
    $spatie = User::factory()->create(['role' => UserRole::User]);
    $spatie->assignRole(Role::findOrCreate('super_admin', 'web'));
    $legacy = User::factory()->create(['role' => UserRole::Student]);
    $this->artisan('users:reconcile-system-roles')->assertSuccessful();
    expect($spatie->fresh()->getRawOriginal('role'))->toBe('user')
        ->and($legacy->fresh()->getRoleNames())->toBeEmpty();
    $this->artisan('users:reconcile-system-roles', ['--apply' => true])->assertSuccessful();
    expect($spatie->fresh()->getRawOriginal('role'))->toBe('super_admin')
        ->and($legacy->fresh()->hasRole('student'))->toBeTrue();
});

it('saves one Spatie system role from the administrator selector', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Developer]);
    $target = User::factory()->create(['role' => UserRole::Student]);
    $target->assignRole(Role::findOrCreate('student', 'web'));
    $this->actingAs($admin)->put(portalUrlForAdministrators('/administrators/users/'.$target->id), [
        'name' => $target->name, 'email' => $target->email, 'role' => 'instructor', 'roles' => [],
    ])->assertRedirect();
    expect($target->fresh()->role)->toBe(UserRole::Instructor)
        ->and($target->fresh()->getRoleNames()->all())->toBe(['instructor']);
});

it('offers only custom bundles in additional access', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Developer]);
    Role::findOrCreate('student', 'web');
    Role::findOrCreate('Super Administrator', 'web');
    Role::findOrCreate('Library support bundle', 'web');
    $response = $this->actingAs($admin)->get(portalUrlForAdministrators('/administrators/users/create'))->assertOk();
    expect(collect($response->inertiaProps('permissions'))->pluck('name')->all())->toContain('Library support bundle')->not->toContain('student', 'Super Administrator');
});
