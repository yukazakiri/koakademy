<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    public function createApplication()
    {
        putenv('APP_ENV=testing');
        $_ENV['APP_ENV'] = 'testing';
        $_SERVER['APP_ENV'] = 'testing';

        putenv('APP_CONFIG_CACHE='.sys_get_temp_dir().'/koakademy_testing_config.php');
        $_ENV['APP_CONFIG_CACHE'] = sys_get_temp_dir().'/koakademy_testing_config.php';
        $_SERVER['APP_CONFIG_CACHE'] = sys_get_temp_dir().'/koakademy_testing_config.php';

        $testDb = dirname(__DIR__).'/database/testing.sqlite';
        if (! file_exists($testDb)) {
            touch($testDb);
        }

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $appDb = $app->databasePath('database.sqlite');
        $currentDb = (string) $app['config']->get('database.connections.sqlite.database');

        if ($currentDb === $appDb || (file_exists($appDb) && file_exists($currentDb) && realpath($currentDb) === realpath($appDb))) {
            $app['config']->set('database.connections.sqlite.database', $testDb);
        }

        return $app;
    }
}
