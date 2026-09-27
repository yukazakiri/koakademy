<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['inertia.ssr.enabled' => false]);

        $this->app->singleton(\Faker\Generator::class, function (): \Faker\Generator {
            return \Faker\Factory::create('en_US');
        });
    }

    protected function tearDown(): void
    {
        if ($this->app && $this->app->bound(\App\Services\TenantContext::class)) {
            $this->app->make(\App\Services\TenantContext::class)->reset();
        }

        // FeatureToggleRegistry caches global Pennant states in a static
        // property. Parallel test workers reuse the PHP process, so a toggle
        // activated in one test can otherwise leak into a later test case.
        if (class_exists(\App\Services\FeatureToggleRegistry::class)) {
            \App\Services\FeatureToggleRegistry::flushGlobalFeatureStates();
        }

        // Pennant also keeps its own driver cache; flush it in addition to the
        // application registry cache before the next test reuses this worker.
        if ($this->app && $this->app->resolved(\Laravel\Pennant\Feature::class)) {
            \Laravel\Pennant\Feature::purge();
        }

        \App\Services\GeneralSettingsService::flushGlobalSetting();

        parent::tearDown();
    }
}
