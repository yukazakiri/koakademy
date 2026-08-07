<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

uses()->group('horizon');

test('horizon config isolates pdf generation into a dedicated supervisor', function (): void {
    $defaults = config('horizon.defaults');

    expect($defaults)->toHaveKey('supervisor-1')
        ->and($defaults)->toHaveKey('supervisor-pdf');

    expect($defaults['supervisor-1']['queue'])->toBe(['default', 'assessments'])
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

test('default redis queue connection uses the plain redis driver', function (): void {
    $redisConnection = config('queue.connections.redis');

    expect($redisConnection)->not->toBeNull();
    expect($redisConnection['driver'])->toBe('redis');
});

test('horizon dashboard is guarded by the horizon authorization gate', function (): void {
    expect(Gate::has('viewHorizon'))->toBeTrue();

    $user = new App\Models\User(['role' => App\Enums\UserRole::SuperAdmin]);
    $admin = new App\Models\User(['role' => App\Enums\UserRole::Admin]);
    $student = new App\Models\User(['role' => App\Enums\UserRole::Student]);

    expect(Gate::forUser($user)->allows('viewHorizon'))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('viewHorizon'))->toBeFalse()
        ->and(Gate::forUser($student)->allows('viewHorizon'))->toBeFalse();
});

test('horizon dashboard allows users with the spatie super admin role', function (): void {
    Role::firstOrCreate(['name' => App\Enums\UserRole::SuperAdmin->value, 'guard_name' => 'web']);

    $user = App\Models\User::factory()->create(['role' => App\Enums\UserRole::Admin]);
    $user->assignRole(App\Enums\UserRole::SuperAdmin);

    expect(Gate::forUser($user)->allows('viewHorizon'))->toBeTrue();
});

test('horizon snapshot is scheduled without station maintenance commands', function (): void {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $horizonSnapshotFound = false;
    $stationCommandFound = false;

    foreach ($schedule->events() as $event) {
        $command = $event->command ?? '';

        if (str_contains($command, 'horizon:snapshot')) {
            $horizonSnapshotFound = true;
            expect($event->expression)->toBe('*/5 * * * *');
        }

        if (str_contains($command, 'station:')) {
            $stationCommandFound = true;
        }
    }

    expect($horizonSnapshotFound)->toBeTrue('horizon:snapshot should be scheduled')
        ->and($stationCommandFound)->toBeFalse('no station commands should be scheduled');
});
