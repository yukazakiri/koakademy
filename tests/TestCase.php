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
}
