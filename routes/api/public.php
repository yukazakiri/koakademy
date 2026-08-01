<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\GeneralSettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 - Public
|--------------------------------------------------------------------------
|
| Endpoints reachable without authentication (marketing site, app
| bootstrap config). Keep payloads free of personal data here.
|
*/

Route::prefix('public')->name('public.')->group(function (): void {
    Route::get('/settings', [GeneralSettingController::class, 'publicWebsiteSettings'])->name('settings');
});
