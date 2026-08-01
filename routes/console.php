<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('station:prune')
    ->daily()
    ->withoutOverlapping();

Schedule::command('station:alerts:check')
    ->everyFiveMinutes()
    ->when(static fn (): bool => (bool) config('station.alerts.enabled', false))
    ->withoutOverlapping();

Schedule::command('assessment-exports:maintain')
    ->everyTenMinutes()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('migrate:fresh --seed --force')
    ->daily()
    ->environments(['demo'])
    ->withoutOverlapping();
