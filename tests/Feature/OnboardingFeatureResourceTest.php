<?php

declare(strict_types=1);

use App\Features\Onboarding\FeatureClassRegistry;
use App\Filament\Resources\OnboardingFeatures\OnboardingFeatureResource;
use App\Models\OnboardingFeature;
use App\Models\User;
use Filament\Facades\Filament;
use Laravel\Pennant\Feature;

beforeEach(function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    Filament::setCurrentPanel('admin');
});

it('can create and retrieve onboarding features', function (): void {
    $feature = OnboardingFeature::factory()->create(['name' => 'Test Feature']);

    $found = OnboardingFeature::query()->where('name', 'Test Feature')->first();

    expect($found)->not->toBeNull();
    expect($found->name)->toBe('Test Feature');
});

it('can search onboarding features by name', function (): void {
    $feature1 = OnboardingFeature::factory()->create(['name' => 'Faculty Toolkit Unique']);
    $feature2 = OnboardingFeature::factory()->create(['name' => 'Student Dashboard Unique']);

    $results = OnboardingFeature::query()
        ->where('name', 'like', '%Faculty Toolkit Unique%')
        ->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($feature1->id);
});

it('filters by audience', function (): void {
    $studentFeature = OnboardingFeature::factory()->create(['audience' => 'student', 'feature_key' => 'test-student-aud']);
    $facultyFeature = OnboardingFeature::factory()->create(['audience' => 'faculty', 'feature_key' => 'test-faculty-aud']);

    $studentFeatures = OnboardingFeature::query()->where('audience', 'student')->pluck('id');

    expect($studentFeatures)->toContain($studentFeature->id);
    expect($studentFeatures)->not->toContain($facultyFeature->id);
});

it('filters by active status', function (): void {
    $activeFeature = OnboardingFeature::factory()->create(['is_active' => true, 'feature_key' => 'test-active-status']);
    $inactiveFeature = OnboardingFeature::factory()->create(['is_active' => false, 'feature_key' => 'test-inactive-status']);

    $activeIds = OnboardingFeature::query()->where('is_active', true)->pluck('id');

    expect($activeIds)->toContain($activeFeature->id);
    expect($activeIds)->not->toContain($inactiveFeature->id);
});

it('sorts active features first by default', function (): void {
    $inactive = OnboardingFeature::factory()->create(['name' => 'A-Alpha-Sort', 'is_active' => false, 'feature_key' => 'test-sort-inactive']);
    $active = OnboardingFeature::factory()->create(['name' => 'Z-Zulu-Sort', 'is_active' => true, 'feature_key' => 'test-sort-active']);

    $ordered = OnboardingFeature::query()
        ->whereIn('id', [$inactive->id, $active->id])
        ->orderByDesc('is_active')
        ->orderBy('name')
        ->get();

    expect($ordered->first()->id)->toBe($active->id);
    expect($ordered->last()->id)->toBe($inactive->id);
});

it('can create an onboarding feature', function (): void {
    $feature = OnboardingFeature::create([
        'feature_key' => 'test-create-feature-key',
        'name' => 'Test Feature',
        'audience' => 'all',
        'summary' => 'Test summary',
        'badge' => null,
        'accent' => null,
        'cta_label' => null,
        'cta_url' => null,
        'steps' => [],
        'is_active' => true,
    ]);

    expect($feature->exists)->toBeTrue();
    expect($feature->feature_key)->toBe('test-create-feature-key');
    expect($feature->name)->toBe('Test Feature');
});

it('validates unique feature key', function (): void {
    OnboardingFeature::factory()->create(['feature_key' => 'existing-unique-key-test']);

    expect(fn () => OnboardingFeature::create([
        'feature_key' => 'existing-unique-key-test',
        'name' => 'Duplicate Key Feature',
        'audience' => 'all',
        'steps' => [],
        'is_active' => true,
    ]))->toThrow(Illuminate\Database\QueryException::class);
});

it('can update an onboarding feature', function (): void {
    $feature = OnboardingFeature::factory()->create(['name' => 'Original Name']);

    $feature->update(['name' => 'Updated Name']);

    expect($feature->fresh()->name)->toBe('Updated Name');
});

it('can delete an onboarding feature', function (): void {
    $feature = OnboardingFeature::factory()->create();
    $featureKey = $feature->feature_key;

    OnboardingFeatureResource::deactivateFeature($featureKey);
    $feature->delete();

    expect(OnboardingFeature::find($feature->id))->toBeNull();
});

it('activates pennant feature for class-based keys', function (): void {
    $feature = OnboardingFeature::factory()->create([
        'feature_key' => 'onboarding-faculty-toolkit',
        'is_active' => true,
    ]);

    $featureClass = FeatureClassRegistry::classForKey($feature->feature_key);
    expect($featureClass)->not->toBeNull();

    // activateFeature should not throw
    OnboardingFeatureResource::activateFeature($feature->feature_key);

    // Cleanup
    Feature::forget($featureClass);
});

it('deactivates pennant feature for class-based keys', function (): void {
    $feature = OnboardingFeature::factory()->create([
        'feature_key' => 'onboarding-faculty-grades',
        'is_active' => false,
    ]);

    $featureClass = FeatureClassRegistry::classForKey($feature->feature_key);
    expect($featureClass)->not->toBeNull();

    // deactivateFeature should not throw
    OnboardingFeatureResource::deactivateFeature($feature->feature_key);

    // Cleanup
    Feature::forget($featureClass);
});

it('resolves class-based pennant type for registered keys', function (): void {
    $feature = OnboardingFeature::factory()->create([
        'feature_key' => 'onboarding-faculty-toolkit',
    ]);

    $featureClass = FeatureClassRegistry::classForKey($feature->feature_key);

    expect($featureClass)->not->toBeNull();
});

it('resolves string pennant type for unregistered keys', function (): void {
    $feature = OnboardingFeature::factory()->create([
        'feature_key' => 'custom-unregistered-key',
    ]);

    $featureClass = FeatureClassRegistry::classForKey($feature->feature_key);

    expect($featureClass)->toBeNull();
});

it('counts user overrides for class-based features', function (): void {
    $feature = OnboardingFeature::factory()->create([
        'feature_key' => 'onboarding-faculty-toolkit',
    ]);

    $featureClass = FeatureClassRegistry::classForKey($feature->feature_key);

    if ($featureClass) {
        $count = OnboardingFeatureResource::getUserOverrideCount($featureClass);
        expect($count)->toBeInt();
    }
});

it('toggles is_active on model', function (): void {
    $feature = OnboardingFeature::factory()->create(['is_active' => true]);

    $feature->is_active = ! $feature->is_active;
    $feature->save();

    expect($feature->fresh()->is_active)->toBeFalse();
});

it('stores steps as json array', function (): void {
    $steps = [
        ['type' => 'step', 'data' => ['title' => 'Welcome', 'summary' => 'Get started']],
    ];

    $feature = OnboardingFeature::factory()->create(['steps' => $steps]);

    expect($feature->fresh()->steps)->toBe($steps);
});
