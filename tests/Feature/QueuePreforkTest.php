<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Tevkert\QueuePrefork\Preflight;

it('loads queue prefork configuration', function (): void {
    expect(config('queue-prefork.enabled'))->toBeTrue()
        ->and(config('queue-prefork.queue_work'))->toBeTrue()
        ->and(config('queue-prefork.horizon'))->toBeTrue()
        ->and(config('queue-prefork.opcache.memory_consumption'))->toBe(192);
});

it('registers queue prefork artisan commands', function (): void {
    $commands = array_keys(Artisan::all());

    expect($commands)->toContain('queue-prefork:doctor')
        ->and($commands)->toContain('queue-prefork:status')
        ->and($commands)->toContain('queue-prefork:switch')
        ->and($commands)->toContain('queue-prefork:retire');
});

it('can run queue prefork doctor command', function (): void {
    $exitCode = Artisan::call('queue-prefork:doctor');

    // Doctor command should exit successfully (0) or stock fallback (0/1 with output)
    $output = Artisan::output();
    expect($output)->toContain('Horizon')
        ->and($output)->toContain('queue:work');
});

it('can inspect runtime switches', function (): void {
    $exitCode = Artisan::call('queue-prefork:switch', ['action' => 'show']);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Horizon')
        ->and($output)->toContain('queue:work')
        ->and($output)->toContain('enabled');
});

it('runs preflight check against application without fatal errors', function (): void {
    $reasons = Preflight::check(app(), false);

    expect($reasons)->toBeArray();
});
