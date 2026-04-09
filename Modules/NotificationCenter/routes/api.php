<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\NotificationCenter\Http\Controllers\NotificationCenterController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('notificationcenters', NotificationCenterController::class)->names('notificationcenter');
});
