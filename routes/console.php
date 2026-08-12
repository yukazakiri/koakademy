<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Horizon is not a project dependency (see bootstrap/providers.php); it is only
// present inside the production Docker images. Guard its scheduled snapshot with
// the same package-presence check so non-Docker installs do not invoke an
// undefined Artisan command from schedule:run.
if (class_exists(Laravel\Horizon\Horizon::class)) {
    Schedule::command('horizon:snapshot')
        ->everyFiveMinutes()
        ->withoutOverlapping();
}

Schedule::command('assessment-exports:maintain')
    ->everyTenMinutes()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('migrate:fresh --seed --force')
    ->daily()
    ->environments(['demo'])
    ->withoutOverlapping();
