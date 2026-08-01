<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\StudentVerificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 - Student Mobile App
|--------------------------------------------------------------------------
|
| Endpoints consumed by the student-facing mobile application. All routes
| are protected by auth:sanctum.
|
*/

Route::middleware(['auth:sanctum'])->prefix('students')->name('students.')->group(function (): void {
    Route::post('/verify', [StudentVerificationController::class, 'verify'])->name('verify');
});
