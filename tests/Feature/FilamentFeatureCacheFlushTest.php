<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Features\Toggles\OnlineCollegeEnrollment;
use App\Http\Middleware\ResetFeatureAccessState;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;

beforeEach(function (): void {
    $this->withoutVite();
});

it('includes ResetFeatureAccessState in admin and portal panel middleware stacks', function (): void {
    $adminPanel = Filament::getPanel('admin');
    $portalPanel = Filament::getPanel('portal');

    expect($adminPanel->getMiddleware())->toContain(ResetFeatureAccessState::class)
        ->and($portalPanel->getMiddleware())->toContain(ResetFeatureAccessState::class);
});

it('re-reads feature flags across sequential requests to filament admin pages without state leak', function (): void {
    DB::table('feature_definitions')->where('key', 'online-college-enrollment')->update([
        'mode' => 'enforced',
        'enabled' => true,
    ]);

    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    // Request 1: hits Filament admin page with feature active
    $this->actingAs($admin)
        ->get(route('filament.admin.pages.dashboard'))
        ->assertOk();

    expect(Feature::active(OnlineCollegeEnrollment::class))->toBeTrue();

    // Toggle the feature definition directly in the database (simulating another worker or admin change)
    DB::table('feature_definitions')->where('key', 'online-college-enrollment')->update([
        'enabled' => false,
    ]);

    // Request 2: hits Filament admin page again
    // Without ResetFeatureAccessState in Filament middleware, Pennant's Decorator
    // would retain its in-memory collection cache and still evaluate to true.
    $this->actingAs($admin)
        ->get(route('filament.admin.pages.dashboard'))
        ->assertOk();

    expect(Feature::active(OnlineCollegeEnrollment::class))->toBeFalse();
});

it('re-reads feature flags across sequential requests to filament portal pages without state leak', function (): void {
    DB::table('feature_definitions')->where('key', 'online-college-enrollment')->update([
        'mode' => 'enforced',
        'enabled' => true,
    ]);

    // Request 1: hits Filament portal page with feature active
    $this->get(route('filament.portal.auth.login'))
        ->assertOk();

    expect(Feature::active(OnlineCollegeEnrollment::class))->toBeTrue();

    // Toggle the feature definition directly in the database
    DB::table('feature_definitions')->where('key', 'online-college-enrollment')->update([
        'enabled' => false,
    ]);

    // Request 2: hits Filament portal page again
    // Without ResetFeatureAccessState in Filament middleware, Pennant's Decorator
    // would retain its in-memory collection cache and still evaluate to true.
    $this->get(route('filament.portal.auth.login'))
        ->assertOk();

    expect(Feature::active(OnlineCollegeEnrollment::class))->toBeFalse();
});

it('flushes pennant in-memory decorator cache when request terminates', function (): void {
    Feature::define('test-transient-flag', fn () => true);

    expect(Feature::active('test-transient-flag'))->toBeTrue();

    $driver = app(Laravel\Pennant\FeatureManager::class)->driver();
    $cacheProp = new ReflectionProperty($driver, 'cache');
    $cacheProp->setAccessible(true);

    expect($cacheProp->getValue($driver))->not->toBeEmpty();

    // Simulate application terminating at end of request/operation
    $this->app->terminate();

    expect($cacheProp->getValue($driver))->toBeEmpty();
});
