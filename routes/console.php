<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule automatic Sanity sync every 15 minutes
Schedule::command('sanity:sync')->everyFifteenMinutes();

// Schedule Horizon metrics snapshots every 5 minutes
Schedule::command('horizon:snapshot')->everyFiveMinutes();
