<?php

declare(strict_types=1);

test('telescope entries are pruned daily on one production server', function (): void {
    /** @var Illuminate\Console\Scheduling\Schedule $schedule */
    $schedule = app(Illuminate\Console\Scheduling\Schedule::class);

    $pruneEvent = collect($schedule->events())->first(
        fn ($event): bool => is_string($event->command)
            && str_contains($event->command, 'telescope:prune'),
    );

    expect($pruneEvent)->not->toBeNull('telescope:prune should be scheduled')
        ->and($pruneEvent->command)->toContain('--hours=24')
        ->and($pruneEvent->expression)->toBe('0 0 * * *')
        ->and($pruneEvent->environments)->toBe(['production'])
        ->and($pruneEvent->onOneServer)->toBeTrue()
        ->and($pruneEvent->withoutOverlapping)->toBeTrue();
});
