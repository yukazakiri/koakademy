<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Announcement\Http\Controllers\AnnouncementController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group([
    'prefix' => 'administrators/announcements',
    'as' => 'administrators.announcements.',
    'middleware' => ['web', 'auth', 'administrators.only'],
], function () {
    Route::get('/', [AnnouncementController::class, 'index'])->name('index');
    Route::post('/', [AnnouncementController::class, 'store'])->name('store');
    Route::put('/{announcement}', [AnnouncementController::class, 'update'])->name('update');
    Route::delete('/{announcement}', [AnnouncementController::class, 'destroy'])->name('destroy');
});
