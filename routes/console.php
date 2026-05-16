<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule Horizon metrics snapshots every 5 minutes
Schedule::command('horizon:snapshot')
    ->everyFiveMinutes()
    ->when(static function (): bool {
        $queueConnection = mb_strtolower((string) config('queue.default', 'database'));
        $horizonEnabled = filter_var(env('HORIZON_ENABLED', true), FILTER_VALIDATE_BOOL);

        return $queueConnection === 'redis' || $horizonEnabled;
    });

Schedule::command('migrate:fresh --seed --force')
    ->daily()
    ->environments(['demo'])
    ->withoutOverlapping();
