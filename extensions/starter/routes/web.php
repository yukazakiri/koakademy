<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Koakademy\SchoolNotes\Http\Controllers\SchoolNoteController;

Route::middleware(['auth', 'administrators.only'])->prefix('administrators/school-notes')->name('administrators.school-notes.')->group(function (): void {
    Route::get('/', [SchoolNoteController::class, 'index'])->name('index');
    Route::post('/', [SchoolNoteController::class, 'store'])->middleware('throttle:30,1')->name('store');
});
