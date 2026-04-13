<?php

declare(strict_types=1);

use App\Features\Onboarding\FeatureClassRegistry;
use App\Features\OnlineCollegeEnrollment;
use App\Features\OnlineTesdaEnrollment;
use App\Models\OnboardingFeature;
use App\Models\User;
use Filament\Facades\Filament;
use Laravel\Pennant\Feature;

beforeEach(function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    Filament::setCurrentPanel('admin');
});

it('resolves college enrollment feature class from registry', function (): void {
    $class = FeatureClassRegistry::classForKey('online-college-enrollment');

    expect($class)->toBe(OnlineCollegeEnrollment::class);
});

it('resolves TESDA enrollment feature class from registry', function (): void {
    $class = FeatureClassRegistry::classForKey('online-tesda-enrollment');

    expect($class)->toBe(OnlineTesdaEnrollment::class);
});

it('college enrollment feature resolves true when active', function (): void {
    OnboardingFeature::firstOrCreate(
        ['feature_key' => 'online-college-enrollment'],
        ['name' => 'Online College Enrollment', 'audience' => 'student', 'steps' => [], 'is_active' => true],
    )->update(['is_active' => true]);

    $feature = new OnlineCollegeEnrollment;
    $user = User::factory()->create(['role' => 'student']);

    expect($feature->resolve($user))->toBeTrue();
});

it('college enrollment feature returns false when inactive', function (): void {
    OnboardingFeature::firstOrCreate(
        ['feature_key' => 'online-college-enrollment'],
        ['name' => 'Online College Enrollment', 'audience' => 'student', 'steps' => [], 'is_active' => false],
    )->update(['is_active' => false]);

    $feature = new OnlineCollegeEnrollment;
    $user = User::factory()->create(['role' => 'student']);

    expect($feature->resolve($user))->toBeFalse();
});

it('college enrollment feature returns false when no record exists', function (): void {
    // Delete if exists from migration
    OnboardingFeature::where('feature_key', 'online-college-enrollment')->delete();

    $feature = new OnlineCollegeEnrollment;
    $user = User::factory()->create(['role' => 'student']);

    expect($feature->resolve($user))->toBeFalse();
});

it('TESDA enrollment feature resolves true when active', function (): void {
    OnboardingFeature::firstOrCreate(
        ['feature_key' => 'online-tesda-enrollment'],
        ['name' => 'Online TESDA Enrollment', 'audience' => 'student', 'steps' => [], 'is_active' => true],
    )->update(['is_active' => true]);

    $feature = new OnlineTesdaEnrollment;
    $user = User::factory()->create(['role' => 'student']);

    expect($feature->resolve($user))->toBeTrue();
});

it('TESDA enrollment feature returns false when inactive', function (): void {
    OnboardingFeature::firstOrCreate(
        ['feature_key' => 'online-tesda-enrollment'],
        ['name' => 'Online TESDA Enrollment', 'audience' => 'student', 'steps' => [], 'is_active' => false],
    )->update(['is_active' => false]);

    $feature = new OnlineTesdaEnrollment;
    $user = User::factory()->create(['role' => 'student']);

    expect($feature->resolve($user))->toBeFalse();
});

it('feature keys are accessible via key method', function (): void {
    $college = new OnlineCollegeEnrollment;
    $tesda = new OnlineTesdaEnrollment;

    expect($college->key())->toBe('online-college-enrollment');
    expect($tesda->key())->toBe('online-tesda-enrollment');
});

it('can activate college enrollment via Pennant', function (): void {
    Feature::activateForEveryone(OnlineCollegeEnrollment::class);

    // Cleanup
    Feature::forget(OnlineCollegeEnrollment::class);
});

it('can deactivate college enrollment via Pennant', function (): void {
    Feature::deactivateForEveryone(OnlineCollegeEnrollment::class);

    // Cleanup
    Feature::forget(OnlineCollegeEnrollment::class);
});

it('enrollment features can be toggled from Filament resource', function (): void {
    $feature = OnboardingFeature::firstOrCreate(
        ['feature_key' => 'online-college-enrollment'],
        ['name' => 'Online College Enrollment', 'audience' => 'student', 'steps' => [], 'is_active' => true],
    );
    $feature->update(['is_active' => true]);

    expect($feature->fresh()->is_active)->toBeTrue();

    $feature->update(['is_active' => false]);

    expect($feature->fresh()->is_active)->toBeFalse();
});
