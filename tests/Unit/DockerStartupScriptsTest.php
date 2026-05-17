<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

function runStartContainerRedisRequirement(array $environment): string
{
    $script = file_get_contents(base_path('docker/start-container'));
    $prefix = mb_strstr($script, "\nif [ \"\$1\" != \"\" ]; then", true);

    expect($prefix)->not->toBeFalse('Unable to isolate docker/start-container function definitions.');

    $testScript = storage_path('framework/testing/start-container-redis-check.sh');
    @mkdir(dirname($testScript), 0777, true);

    file_put_contents($testScript, $prefix."\nif is_redis_required; then\n    echo required\nelse\n    echo skipped\nfi\n");

    $process = new Process(['sh', $testScript], base_path(), $environment);
    $process->run();

    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput() ?: $process->getOutput());

    return mb_trim($process->getOutput());
}

test('docker startup scripts are valid posix shell', function (): void {
    $scripts = [
        base_path('docker/start-container'),
        base_path('docker/docker-scripts/scout-index.sh'),
        base_path('docker/run-scripts.sh'),
    ];

    foreach ($scripts as $script) {
        $process = new Process(['sh', '-n', $script], base_path());
        $process->run();

        expect($process->isSuccessful())
            ->toBeTrue($process->getErrorOutput() ?: $process->getOutput());
    }
});

test('primary-only startup tasks stay scoped to the http container', function (): void {
    $script = file_get_contents(base_path('docker/start-container'));

    expect($script)->toContain('run_primary_startup_tasks');
    expect($script)->toContain('if is_primary_container; then');
    expect($script)->toContain('FORCE_OPTIMIZE_CLEAR');
    expect($script)->toContain('RUN_OPTIMIZE');
});

test('docker startup dependency checks are driven by configured services', function (): void {
    $script = file_get_contents(base_path('docker/start-container'));

    expect($script)->toContain('resolve_database_connection()');
    expect($script)->toContain('is_database_connection_networked()');
    expect($script)->toContain('case "${database_connection}" in');
    expect($script)->toContain('sqlite)');
    expect($script)->toContain('Skipping Database network check (DB_CONNECTION=${database_connection}).');
    expect($script)->toContain('resolve_database_connection');
    expect($script)->toContain('if is_database_connection_networked; then');
    expect($script)->toContain('Skipping Redis check (no Redis-backed services configured).');
});

test('docker startup does not require redis for default laravel services with pulse redis ingest', function (): void {
    expect(runStartContainerRedisRequirement([
        'QUEUE_CONNECTION' => 'sync',
        'CACHE_STORE' => 'database',
        'SESSION_DRIVER' => 'database',
        'PULSE_ENABLED' => 'true',
        'PULSE_INGEST_DRIVER' => 'redis',
    ]))->toBe('skipped');
});

test('docker startup still requires redis for redis backed application services', function (): void {
    expect(runStartContainerRedisRequirement([
        'QUEUE_CONNECTION' => 'sync',
        'CACHE_STORE' => 'redis',
        'SESSION_DRIVER' => 'database',
        'PULSE_ENABLED' => 'true',
        'PULSE_INGEST_DRIVER' => 'storage',
    ]))->toBe('required');
});

test('docker startup migrations seed demo environments', function (): void {
    $script = file_get_contents(base_path('docker/start-container'));

    expect($script)->toContain('if [ "${app_env}" = "demo" ]; then');
    expect($script)->toContain('php artisan migrate --seed --force --no-interaction');
    expect($script)->toContain('php artisan migrate --force --no-interaction');
});

test('scout indexing script stays portable and configurable', function (): void {
    $script = file_get_contents(base_path('docker/docker-scripts/scout-index.sh'));

    expect($script)->not->toContain('local ');
    expect($script)->toContain('SCOUT_INDEX_MODELS');
    expect($script)->toContain('class_uses_recursive');
});
