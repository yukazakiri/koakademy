<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\EnterpriseReporting\Http\Controllers\ConsolidatedReportController;

Route::get('/administrators/consolidated-reports', [ConsolidatedReportController::class, 'index'])
    ->name('administrators.consolidated-reports.index');
Route::get('/administrators/consolidated-reports/export', [ConsolidatedReportController::class, 'export'])
    ->middleware('throttle:10,1')->name('administrators.consolidated-reports.export');
