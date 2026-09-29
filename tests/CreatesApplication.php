<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Tests\Support\TestingDatabase;

trait CreatesApplication
{
    public function createApplication()
    {
        $testDbReal = TestingDatabase::ensureExists();

        putenv('APP_ENV=testing');
        $_ENV['APP_ENV'] = 'testing';
        $_SERVER['APP_ENV'] = 'testing';

        putenv('DB_CONNECTION=sqlite');
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_CONNECTION'] = 'sqlite';

        putenv("DB_DATABASE={$testDbReal}");
        $_ENV['DB_DATABASE'] = $testDbReal;
        $_SERVER['DB_DATABASE'] = $testDbReal;

        putenv("PULSE_DB_DATABASE={$testDbReal}");
        $_ENV['PULSE_DB_DATABASE'] = $testDbReal;
        $_SERVER['PULSE_DB_DATABASE'] = $testDbReal;

        putenv('APP_CONFIG_CACHE='.sys_get_temp_dir().'/koakademy_testing_config.php');
        $_ENV['APP_CONFIG_CACHE'] = sys_get_temp_dir().'/koakademy_testing_config.php';
        $_SERVER['APP_CONFIG_CACHE'] = sys_get_temp_dir().'/koakademy_testing_config.php';

        putenv('APP_ROUTES_CACHE='.sys_get_temp_dir().'/koakademy_testing_routes.php');
        $_ENV['APP_ROUTES_CACHE'] = sys_get_temp_dir().'/koakademy_testing_routes.php';
        $_SERVER['APP_ROUTES_CACHE'] = sys_get_temp_dir().'/koakademy_testing_routes.php';

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', $testDbReal);
        $app['config']->set('database.connections.pulse.database', $testDbReal);

        if ($app->bound('db')) {
            $app['db']->purge('sqlite');
            $app['db']->purge('pulse');
        }

        return $app;
    }
}
