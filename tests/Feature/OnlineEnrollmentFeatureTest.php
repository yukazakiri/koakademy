<?php

declare(strict_types=1);

use App\Features\Toggles\OnlineCollegeEnrollment;
use App\Features\Toggles\OnlineTesdaEnrollment;
use App\Models\User;
use App\Services\FeatureToggleRegistry;
use Laravel\Pennant\Feature;

beforeEach(function (): void {
    Feature::purge(OnlineCollegeEnrollment::class);
    Feature::purge(OnlineTesdaEnrollment::class);
});

afterEach(function (): void {
    Feature::purge(OnlineCollegeEnrollment::class);
    Feature::purge(OnlineTesdaEnrollment::class);
});

it('resolves college enrollment feature class from registry', function (): void {
    $class = FeatureToggleRegistry::classForKey('online-college-enrollment');

    expect($class)->toBe(OnlineCollegeEnrollment::class);
});

it('resolves TESDA enrollment feature class from registry', function (): void {
    $class = FeatureToggleRegistry::classForKey('online-tesda-enrollment');

    expect($class)->toBe(OnlineTesdaEnrollment::class);
});

it('college enrollment feature resolves true by default for all users', function (): void {
    $user = User::factory()->create();

    expect(Feature::for($user)->active(OnlineCollegeEnrollment::class))->toBeTrue();
});

it('college enrollment feature can be deactivated for a specific user', function (): void {
    $user = User::factory()->create();

    Feature::for($user)->deactivate(OnlineCollegeEnrollment::class);

    expect(Feature::for($user)->active(OnlineCollegeEnrollment::class))->toBeFalse();
});

it('college enrollment feature can be activated for a specific user', function (): void {
    $user = User::factory()->create();

    Feature::for($user)->activate(OnlineCollegeEnrollment::class);

    expect(Feature::for($user)->active(OnlineCollegeEnrollment::class))->toBeTrue();
});

it('TESDA enrollment feature resolves true by default for all users', function (): void {
    $user = User::factory()->create();

    expect(Feature::for($user)->active(OnlineTesdaEnrollment::class))->toBeTrue();
});

it('feature keys are accessible via key method', function (): void {
    $college = new OnlineCollegeEnrollment;
    $tesda = new OnlineTesdaEnrollment;

    expect($college->key())->toBe('online-college-enrollment');
    expect($tesda->key())->toBe('online-tesda-enrollment');
});

it('can activate college enrollment globally via Pennant', function (): void {
    Feature::activateForEveryone(OnlineCollegeEnrollment::class);

    $user = User::factory()->create();
    expect(Feature::for($user)->active(OnlineCollegeEnrollment::class))->toBeTrue();
});

it('can deactivate college enrollment for a specific user', function (): void {
    $user = User::factory()->create();

    Feature::for($user)->deactivate(OnlineCollegeEnrollment::class);

    expect(Feature::for($user)->active(OnlineCollegeEnrollment::class))->toBeFalse();

    // Other users should still be active
    $otherUser = User::factory()->create();
    expect(Feature::for($otherUser)->active(OnlineCollegeEnrollment::class))->toBeTrue();
});

it('per-user activation overrides global state', function (): void {
    $user = User::factory()->create();

    Feature::for($user)->activate(OnlineCollegeEnrollment::class);
    expect(Feature::for($user)->active(OnlineCollegeEnrollment::class))->toBeTrue();

    Feature::for($user)->deactivate(OnlineCollegeEnrollment::class);
    expect(Feature::for($user)->active(OnlineCollegeEnrollment::class))->toBeFalse();
});
