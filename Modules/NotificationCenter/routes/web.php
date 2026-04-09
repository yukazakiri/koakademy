<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\NotificationCenter\Http\Controllers\NotificationCenterController;

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
    'prefix' => 'administrators/notifications',
    'as' => 'administrators.notifications.',
    'middleware' => ['web', 'auth', 'role:admin|super_admin|developer'],
], function () {
    Route::get('/', [NotificationCenterController::class, 'index'])->name('index');
    Route::post('/preview', [NotificationCenterController::class, 'preview'])->name('preview');
    Route::post('/', [NotificationCenterController::class, 'store'])->name('store');
});
