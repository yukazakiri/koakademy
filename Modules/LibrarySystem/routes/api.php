<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\LibrarySystem\Http\Controllers\LibrarySystemController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('librarysystems', LibrarySystemController::class)->names('librarysystem');
});
