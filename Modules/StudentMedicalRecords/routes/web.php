<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\StudentMedicalRecords\Http\Controllers\StudentMedicalRecordsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('studentmedicalrecords', StudentMedicalRecordsController::class)->names('studentmedicalrecords');
});
