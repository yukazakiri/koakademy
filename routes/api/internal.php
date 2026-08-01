<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ActiveJobsController;
use App\Http\Controllers\Api\OrganizationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Internal JSON endpoints for the first-party Inertia SPA and mobile app.
|--------------------------------------------------------------------------
|
| Two middleware profiles are mounted by the loader (routes/api.php):
|   - 'web'    -> session-authenticated (Inertia SPA, CSRF-protected)
|   - 'sanctum'-> token-authenticated (mobile app, under /api/v1)
|
*/

Route::prefix('jobs')->name('jobs.')->group(function (): void {
    Route::get('/', [ActiveJobsController::class, 'index'])->name('index');
    Route::get('/{jobId}', [ActiveJobsController::class, 'show'])->name('show');
    Route::delete('/{jobId}', [ActiveJobsController::class, 'dismiss'])->name('dismiss');
});

Route::prefix('organizations')->name('organizations.')->group(function (): void {
    Route::get('/', [OrganizationController::class, 'index'])->name('index');
    Route::get('/current', [OrganizationController::class, 'current'])->name('current');
    Route::post('/switch', [OrganizationController::class, 'switch'])->name('switch');
    Route::post('/', [OrganizationController::class, 'store'])->name('store');
    Route::put('/{id}', [OrganizationController::class, 'update'])->whereNumber('id')->name('update');
    Route::delete('/context', [OrganizationController::class, 'clear'])->name('clear-context');
});
