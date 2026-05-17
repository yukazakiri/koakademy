<?php

declare(strict_types=1);

use App\Features\Toggles\StudentDashboard;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\OnboardingDismissal;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;

use function Pest\Laravel\get;

it('shares onboarding flag on login page', function (): void {
    config(['onboarding.force_on_login' => true]);

    config(['inertia.testing.ensure_pages_exist' => false]);

    get(portalUrlForAdministrators('/login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('login')
            ->where('onboarding.forceOnLogin', true)
        );
});

it('shares active onboarding features for a student', function (): void {
    config(['inertia.testing.ensure_pages_exist' => false]);

    $user = User::factory()->create(['role' => 'student']);

    Feature::activateForEveryone(StudentDashboard::class);

    $request = Request::create('/student/dashboard');
    $request->setUserResolver(static fn (): User => $user);

    $shared = app(HandleInertiaRequests::class)->share($request);

    $featureKeys = collect($shared['onboarding']['features'] ?? [])
        ->pluck('featureKey')
        ->toArray();

    expect($featureKeys)->toContain('student-dashboard');

    Feature::forget(StudentDashboard::class);
});

it('omits dismissed onboarding features', function (): void {
    config(['inertia.testing.ensure_pages_exist' => false]);

    $user = User::factory()->create(['role' => 'student']);

    Feature::activateForEveryone(StudentDashboard::class);

    OnboardingDismissal::factory()->create([
        'user_id' => $user->id,
        'feature_key' => 'student-dashboard',
    ]);

    $request = Request::create('/student/dashboard');
    $request->setUserResolver(static fn (): User => $user);

    $shared = app(HandleInertiaRequests::class)->share($request);

    $featureKeys = collect($shared['onboarding']['features'] ?? [])
        ->pluck('featureKey')
        ->toArray();

    // student-dashboard should be omitted because it was dismissed
    expect($featureKeys)->not->toContain('student-dashboard');

    Feature::forget(StudentDashboard::class);
});
