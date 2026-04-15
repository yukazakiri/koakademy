<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

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

test('scout indexing script stays portable and configurable', function (): void {
    $script = file_get_contents(base_path('docker/docker-scripts/scout-index.sh'));

    expect($script)->not->toContain('local ');
    expect($script)->toContain('SCOUT_INDEX_MODELS');
    expect($script)->toContain('class_uses_recursive');
});
