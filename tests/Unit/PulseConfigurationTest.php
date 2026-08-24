<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('uses the application database for Pulse storage by default', function (): void {
    $process = new Process([
        PHP_BINARY,
        '-d',
        'memory_limit=512M',
        '-r',
        <<<'PHP'
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        echo config('database.connections.pulse.database');
        PHP,
    ], base_path(), [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'pgsql',
        'DB_DATABASE' => 'koakademy',
        'PULSE_DB_DATABASE' => false,
    ]);

    $process->mustRun();

    expect(mb_trim($process->getOutput()))->toBe('koakademy');
});
