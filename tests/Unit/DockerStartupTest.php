<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * @return array{exit_code: int|null, output: string, migrations: string}
 */
function runDockerMigrationDecision(?string $autoMigrate = null, ?string $legacyRunMigrations = null): array
{
    $source = file_get_contents(base_path('docker/start-container'));

    if ($source === false) {
        throw new RuntimeException('Unable to read the Docker startup script.');
    }

    $bootstrapMarker = "\nif [ \"\$1\" != \"\" ]; then";
    $bootstrapOffset = mb_strpos($source, $bootstrapMarker);

    if ($bootstrapOffset === false) {
        throw new RuntimeException('Unable to isolate the Docker startup functions.');
    }

    $script = mb_substr($source, 0, $bootstrapOffset)."\nrun_migrations_if_enabled\n";
    $filesystem = new Filesystem();
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'koakademy-docker-start-'.bin2hex(random_bytes(6));
    $filesystem->mkdir($directory);

    try {
        $scriptPath = $directory.DIRECTORY_SEPARATOR.'start-container.sh';
        $phpPath = $directory.DIRECTORY_SEPARATOR.'php';
        $migrationLogPath = $directory.DIRECTORY_SEPARATOR.'migrations.log';

        file_put_contents($scriptPath, $script);
        file_put_contents($phpPath, <<<'SH'
#!/usr/bin/env sh

if [ "${1:-}" = "artisan" ] && [ "${2:-}" = "migrate" ]; then
    printf '%s\n' "$*" >> "${MIGRATION_LOG}"
fi
SH);
        @chmod($scriptPath, 0755);
        @chmod($phpPath, 0755);

        $environment = getenv();
        $pathKey = 'PATH';

        foreach (array_keys($environment) as $environmentKey) {
            if (mb_strtolower($environmentKey) === 'path') {
                $pathKey = $environmentKey;
                break;
            }
        }

        $shell = dockerStartupShell();
        $environment[$pathKey] = $directory.PATH_SEPARATOR.dirname($shell).PATH_SEPARATOR.($environment[$pathKey] ?? '');
        $environment['APP_ENV'] = 'production';
        $environment['DB_CONNECTION'] = 'sqlite';
        $environment['MIGRATION_LOG'] = $migrationLogPath;
        $environment['PRODUCTION_SEEDERS'] = '';
        $environment['AUTO_MIGRATE'] = $autoMigrate ?? '';
        $environment['RUN_MIGRATIONS'] = $legacyRunMigrations ?? '';

        $process = new Process([
            $shell,
            $scriptPath,
        ], base_path(), $environment);
        $process->run();

        return [
            'exit_code' => $process->getExitCode(),
            'output' => $process->getOutput().$process->getErrorOutput(),
            'migrations' => is_file($migrationLogPath) ? mb_trim((string) file_get_contents($migrationLogPath)) : '',
        ];
    } finally {
        $filesystem->remove($directory);
    }
}

function dockerStartupShell(): string
{
    if (PHP_OS_FAMILY !== 'Windows') {
        return 'sh';
    }

    foreach ([
        'C:\\Program Files\\Git\\usr\\bin\\sh.exe',
        'C:\\Program Files\\Git\\bin\\sh.exe',
    ] as $shell) {
        if (is_file($shell)) {
            return $shell;
        }
    }

    throw new RuntimeException('A POSIX shell is required to test the Docker startup script.');
}

it('runs database migrations by default and supports an explicit opt out', function (): void {
    $default = runDockerMigrationDecision();
    $disabled = runDockerMigrationDecision('false');

    expect($default['migrations'])->toBe('artisan migrate --force --no-interaction')
        ->and($disabled['migrations'])->toBe('')
        ->and($disabled['output'])->toContain('Database migrations disabled, skipping.');
});

it('gives AUTO_MIGRATE precedence over the legacy migration setting', function (): void {
    $enabled = runDockerMigrationDecision('true', 'false');
    $disabled = runDockerMigrationDecision('false', 'true');

    expect($enabled['migrations'])->toBe('artisan migrate --force --no-interaction')
        ->and($disabled['migrations'])->toBe('');
});
