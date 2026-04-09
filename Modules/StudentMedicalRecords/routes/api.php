<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\StudentMedicalRecords\Http\Controllers\StudentMedicalRecordsController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('studentmedicalrecords', StudentMedicalRecordsController::class)->names('studentmedicalrecords');
});
