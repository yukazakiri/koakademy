<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;

uses()->group('horizon');

test('horizon config isolates pdf generation into dedicated supervisor', function (): void {
    $defaults = config('horizon.defaults');

    expect($defaults)->toHaveKey('supervisor-default')
        ->and($defaults)->toHaveKey('supervisor-pdf');

    expect($defaults['supervisor-default']['queue'])->toBe(['default', 'assessments'])
        ->and($defaults['supervisor-pdf']['queue'])->toBe(['pdf-generation']);
});

test('pdf supervisor uses dedicated redis connection with elevated timeout and memory', function (): void {
    $pdfSupervisor = config('horizon.defaults.supervisor-pdf');

    expect($pdfSupervisor['connection'])->toBe('redis-pdf')
        ->and($pdfSupervisor['timeout'])->toBe(3600)
        ->and($pdfSupervisor['memory'])->toBe(2048)
        ->and($pdfSupervisor['balance'])->toBe('auto');
});

test('pdf queue connection has retry_after greater than longest job timeout', function (): void {
    $pdfConnection = config('queue.connections.redis-pdf');

    expect($pdfConnection)->not->toBeNull();
    expect($pdfConnection['driver'])->toBe('redis');
    expect($pdfConnection['connection'])->toBe('queue-pdf');
    expect($pdfConnection['retry_after'])->toBe(7200);
});

test('horizon waits include thresholds for pdf generation queue', function (): void {
    $waits = config('horizon.waits');

    expect($waits)->toHaveKey('redis:default')
        ->and($waits)->toHaveKey('redis:assessments')
        ->and($waits)->toHaveKey('redis-pdf:pdf-generation');

    expect($waits['redis-pdf:pdf-generation'])->toBe(300);
});

test('horizon snapshot is scheduled every five minutes', function (): void {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $found = false;
    foreach ($schedule->events() as $event) {
        if (str_contains($event->command, 'horizon:snapshot')) {
            $found = true;
            expect($event->expression)->toBe('*/5 * * * *');
        }
    }

    expect($found)->toBeTrue('horizon:snapshot should be scheduled');
});
