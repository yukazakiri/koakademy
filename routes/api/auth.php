<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\AuthTokenController;
use App\Http\Controllers\Api\V1\Auth\SignupController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 - Authentication
|--------------------------------------------------------------------------
|
| Token issuing (login/logout, API keys) plus the self-service signup
| flow for students and faculty. Rate-limited tighter than data routes.
|
*/

Route::middleware(['throttle:api-login'])->prefix('auth')->name('auth.')->group(function (): void {
    Route::post('/login', [AuthTokenController::class, 'store'])->name('login');

    // Self-service signup (OTP based)
    Route::post('/signup/email-lookup', [SignupController::class, 'emailLookup'])->name('signup.email-lookup');
    Route::post('/signup/send-otp', [SignupController::class, 'sendOtp'])->name('signup.send-otp');
    Route::post('/signup', [SignupController::class, 'signup'])->name('signup');
});

Route::middleware(['auth:sanctum'])->prefix('auth')->name('auth.')->group(function (): void {
    Route::get('/me', [AuthTokenController::class, 'show'])->name('me');
    Route::post('/logout', [AuthTokenController::class, 'destroy'])->name('logout');

    // API keys for external integrations
    Route::get('/tokens', [AuthTokenController::class, 'index'])->name('tokens.index');
    Route::post('/tokens', [AuthTokenController::class, 'storeToken'])->name('tokens.store');
    Route::delete('/tokens/{tokenId}', [AuthTokenController::class, 'destroyToken'])
        ->whereNumber('tokenId')
        ->name('tokens.destroy');
});
