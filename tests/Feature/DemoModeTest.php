<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\followingRedirects;
use function Pest\Laravel\post;

function useDemoEnvironment(): void
{
    app()->detectEnvironment(static fn (): string => 'demo');
}

function createDemoUser(string $email, UserRole $role): User
{
    Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);

    $user = User::factory()->create([
        'name' => (string) $role->getLabel(),
        'email' => $email,
        'role' => $role,
        'email_verified_at' => now(),
    ]);

    $user->assignRole($role->value);

    return $user;
}

it('schedules a daily destructive demo database refresh only for demo environment', function (): void {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $refreshEvent = null;

    foreach ($schedule->events() as $event) {
        if (str_contains($event->command, 'migrate:fresh')) {
            $refreshEvent = $event;
            break;
        }
    }

    expect($refreshEvent)->not->toBeNull('migrate:fresh should be scheduled for demo refresh')
        ->and($refreshEvent->command)->toContain('--seed')
        ->and($refreshEvent->command)->toContain('--force')
        ->and($refreshEvent->expression)->toBe('0 0 * * *')
        ->and($refreshEvent->environments)->toBe(['demo']);
});

it('shares demo mode details only when the application environment is demo', function (): void {
    useDemoEnvironment();

    $request = Request::create('/login');
    $shared = app(HandleInertiaRequests::class)->share($request);

    expect($shared['demoMode']['enabled'])->toBeTrue()
        ->and($shared['demoMode']['accounts'])->toHaveCount(3)
        ->and($shared['demoMode']['accounts'][0]['role'])->toBe('student');
});

it('logs in a fixed student demo account in demo environment', function (): void {
    useDemoEnvironment();

    $student = createDemoUser('john.student@student.koakademy.edu', UserRole::Student);

    $token = 'demo-login-csrf-token';

    $this->withSession(['_token' => $token]);

    post(portalUrlForAdministrators('/demo-login/student'), ['_token' => $token])
        ->assertRedirect('/student/dashboard');

    $this->assertAuthenticatedAs($student);
});

it('rejects demo login outside demo environment', function (): void {
    createDemoUser('admin@koakademy.edu', UserRole::Admin);

    post(portalUrlForAdministrators('/demo-login/admin'))
        ->assertNotFound();

    $this->assertGuest();
});

it('does not register the service worker in demo environment', function (): void {
    useDemoEnvironment();

    followingRedirects()
        ->get('https://portal.dccp.test/login')
        ->assertOk()
        ->assertSee('serviceWorker.getRegistrations', false)
        ->assertDontSee('src="https://portal.dccp.test/sw.js"', false);
});
