<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Maatwebsite installs the sheet or import being written as
        // PhpSpreadsheet's process-wide static value binder and never restores
        // the default. Exports that bind values as text therefore poison every
        // spreadsheet built later in the same process, which is how a CHED Form
        // B/C report came to store its totals as text and read zero. Reset it
        // between tests so no test inherits another's global spreadsheet state.
        Cell::setValueBinder(new DefaultValueBinder);

        $appDb = database_path('database.sqlite');
        $activeDb = (string) config('database.connections.'.config('database.default').'.database');

        if ($activeDb !== ':memory:' && file_exists($appDb) && file_exists($activeDb) && realpath($activeDb) === realpath($appDb)) {
            throw new RuntimeException(
                "DANGER: Test suite attempted to run against application database ({$appDb}). Aborting to prevent data corruption."
            );
        }

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
