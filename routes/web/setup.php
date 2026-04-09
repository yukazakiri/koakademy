<?php

declare(strict_types=1);

use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web'])->group(function () {
    Route::get('/setup', [SetupController::class, 'show'])->name('setup.show');
    Route::post('/setup', [SetupController::class, 'store'])->name('setup.store');
});
