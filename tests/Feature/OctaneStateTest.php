<?php

declare(strict_types=1);

use App\Models\GeneralSetting;
use App\Modules\CompatibilityChecker;
use App\Modules\ModuleManifestRepository;
use App\Modules\ModuleStateRepository;
use App\Services\FeatureAccessService;
use App\Services\GeneralSettingsService;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Announcement\Models\Announcement;

it('resets request-scoped state between Octane requests', function (string $abstract): void {
    $first = app($abstract);

    app()->forgetScopedInstances();

    expect(app($abstract))->not->toBe($first);
})->with([
    GeneralSettingsService::class,
    TenantContext::class,
    FeatureAccessService::class,
    ModuleStateRepository::class,
    ModuleManifestRepository::class,
    CompatibilityChecker::class,
]);

it('keeps request state out of static properties', function (string $class): void {
    $staticProperties = array_filter(
        (new ReflectionClass($class))->getProperties(ReflectionProperty::IS_STATIC),
        static fn (ReflectionProperty $property): bool => $property->getDeclaringClass()->getName() === $class,
    );

    expect($staticProperties)->toBe([]);
})->with([
    GeneralSettingsService::class,
    TenantContext::class,
    FeatureAccessService::class,
    ModuleStateRepository::class,
    ModuleManifestRepository::class,
    Announcement::class,
]);

it('reloads global settings when another worker changes them', function (): void {
    GeneralSetting::factory()->create(['site_name' => 'Old value']);

    $workerOne = app(GeneralSettingsService::class);

    expect($workerOne->getGlobalSetting('site_name'))->toBe('Old value');

    // Simulate another worker updating the row and invalidating the shared cache
    // without touching this worker's process-local state.
    DB::table('general_settings')->update(['site_name' => 'New value']);
    Cache::forget('general_settings_id');

    app()->forgetScopedInstances();

    $workerTwo = app(GeneralSettingsService::class);

    expect($workerTwo->getGlobalSetting('site_name'))->toBe('New value');
});

it('reads announcement schema columns after the shared cache is refreshed', function (): void {
    Cache::put('announcement_schema_columns', []);

    expect(Announcement::schemaHasCachedColumn('id'))->toBeFalse();

    Cache::put('announcement_schema_columns', ['id']);

    expect(Announcement::schemaHasCachedColumn('id'))->toBeTrue();
});

it('flushes request-state bindings before the next Octane request', function (): void {
    expect(config('octane.flush'))
        ->toContain(GeneralSettingsService::class)
        ->toContain(TenantContext::class)
        ->toContain(FeatureAccessService::class)
        ->toContain(ModuleStateRepository::class)
        ->toContain(ModuleManifestRepository::class);
});
