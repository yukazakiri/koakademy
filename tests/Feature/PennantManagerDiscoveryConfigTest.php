<?php

declare(strict_types=1);

use App\Features\Toggles\FacultyDashboard;
use App\Features\Toggles\OnlineCollegeEnrollment;
use App\Features\Toggles\OnlineTesdaEnrollment;
use App\Features\Toggles\StudentDashboard;
use App\Filament\Plugins\PennantManager\Services\FeatureDiscovery;
use App\Models\User;
use App\Services\FeatureToggleRegistry;
use Illuminate\Support\Facades\DB;

it('discovers explicit class based feature definitions from app service provider', function (): void {
    expect(config('pennant-manager.discovered_features'))->toBe([]);

    $discoveredFeatures = FeatureDiscovery::discover();

    expect($discoveredFeatures)
        ->toContain(FacultyDashboard::class)
        ->toContain(StudentDashboard::class)
        ->toContain(OnlineCollegeEnrollment::class)
        ->toContain(OnlineTesdaEnrollment::class)
        ->and($discoveredFeatures)->toHaveCount(count(FeatureToggleRegistry::allClasses()));
});

it('imports discovered features as global rows for the pennant manager table', function (): void {
    $featureNames = FeatureToggleRegistry::allClasses();

    $globalFeatureNames = DB::table('features')
        ->where('scope', '__laravel_null')
        ->whereIn('name', $featureNames)
        ->pluck('name')
        ->all();

    expect($globalFeatureNames)
        ->toHaveCount(count($featureNames))
        ->toContain(FacultyDashboard::class)
        ->toContain(StudentDashboard::class);
});

it('respects pennant manager json disabled values in feature resolvers', function (): void {
    DB::table('features')
        ->where('name', OnlineCollegeEnrollment::class)
        ->where('scope', '__laravel_null')
        ->update(['value' => json_encode(['enabled' => false], JSON_THROW_ON_ERROR)]);

    $user = User::factory()->create();

    expect((new OnlineCollegeEnrollment())->resolve($user))->toBeFalse();
});
