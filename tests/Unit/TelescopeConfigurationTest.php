<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('uses the application database connection for Telescope storage by default', function (): void {
    expect(config('telescope.storage.database.connection'))
        ->toBe(config('database.default'));
});

it('uses a valid Telescope connection when booting without an environment file', function (): void {
    $process = new Process([
        PHP_BINARY,
        '-d',
        'memory_limit=512M',
        '-r',
        <<<'PHP'
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->loadEnvironmentFrom('.env.docker-build-missing');
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        echo config('telescope.storage.database.connection');
        PHP,
    ], base_path(), [
        'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
        'DB_CONNECTION' => false,
        'TELESCOPE_DB_CONNECTION' => false,
    ]);

    $process->mustRun();

    expect(mb_trim($process->getOutput()))->toBe('sqlite');
});
