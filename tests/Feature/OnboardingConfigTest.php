<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\OnboardingDismissal;
use App\Models\OnboardingFeature;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;

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

    $feature = OnboardingFeature::factory()->create([
        'feature_key' => 'onboarding-student',
        'audience' => 'student',
        'is_active' => true,
    ]);

    config(['pennant.features' => [$feature->feature_key => true]]);

    $request = Request::create('/student/dashboard');
    $request->setUserResolver(static fn (): User => $user);

    $shared = app(HandleInertiaRequests::class)->share($request);

    expect($shared['onboarding']['features'])
        ->toHaveCount(1)
        ->and($shared['onboarding']['features'][0]['featureKey'])->toBe($feature->feature_key);
});

it('omits dismissed onboarding features', function (): void {
    config(['inertia.testing.ensure_pages_exist' => false]);

    $user = User::factory()->create(['role' => 'student']);

    $feature = OnboardingFeature::factory()->create([
        'feature_key' => 'onboarding-student',
        'audience' => 'student',
        'is_active' => true,
    ]);

    config(['pennant.features' => [$feature->feature_key => true]]);

    OnboardingDismissal::factory()->create([
        'user_id' => $user->id,
        'feature_key' => $feature->feature_key,
    ]);

    $request = Request::create('/student/dashboard');
    $request->setUserResolver(static fn (): User => $user);

    $shared = app(HandleInertiaRequests::class)->share($request);

    expect($shared['onboarding']['features'])->toBe([]);
});
