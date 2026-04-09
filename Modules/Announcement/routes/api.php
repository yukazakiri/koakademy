<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Announcement\Http\Controllers\AnnouncementController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('announcements', AnnouncementController::class)->names('announcement');
});
