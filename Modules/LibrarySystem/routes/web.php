<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\LibrarySystem\Http\Controllers\LibrarySystemController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('librarysystems', LibrarySystemController::class)->names('librarysystem');
});
