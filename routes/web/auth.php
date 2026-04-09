<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FacultyVerificationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\SignupEmailLookupController;
use App\Http\Controllers\SignupOtpController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
|
| Routes for user authentication including login, signup, password reset,
| faculty verification, and legal pages.
|
*/

// Root redirect based on auth state
Route::get('/', function () {
    if (Auth::guard('web')->check()) {
        $user = Auth::user();

        if ($user && method_exists($user, 'isAdministrative') && $user->isAdministrative()) {
            return redirect('/administrators');
        }

        // Check if user is a student
        if ($user && $user->role && method_exists($user->role, 'isStudent') && $user->role->isStudent()) {
            return redirect('/student/dashboard');
        }

        return redirect('/faculty/dashboard');
    }

    if (Auth::guard('portal')->check()) {
        return redirect('/portal');
    }

    return redirect('/login');
});

Route::middleware('auth')->get('/dashboard', function () {
    $user = Auth::user();

    if ($user && method_exists($user, 'isAdministrative') && $user->isAdministrative()) {
        return redirect()->route('administrators.dashboard');
    }

    if ($user && $user->role && method_exists($user->role, 'isStudent') && $user->role->isStudent()) {
        return redirect()->route('student.dashboard');
    }

    return redirect()->route('faculty.dashboard');
})->name('dashboard.redirect');

// Login/Logout
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::post('/onboarding/dismiss', [App\Http\Controllers\OnboardingDismissalController::class, 'store'])
    ->middleware('auth')
    ->name('onboarding.dismiss');

// Two Factor Challenge
Route::get('/two-factor-challenge', [App\Http\Controllers\TwoFactorChallengeController::class, 'create'])->name('two-factor.login');
Route::post('/two-factor-challenge', [App\Http\Controllers\TwoFactorChallengeController::class, 'store']);
Route::post('/two-factor-challenge/send-email', [App\Http\Controllers\TwoFactorChallengeController::class, 'sendEmailCode'])->name('two-factor.send-email');
Route::post('/two-factor-challenge/passkey-options', [App\Http\Controllers\TwoFactorChallengeController::class, 'passkeyOptions'])->name('two-factor.passkey-options');
Route::post('/two-factor-challenge/passkey-verify', [App\Http\Controllers\TwoFactorChallengeController::class, 'passkeyVerify'])->name('two-factor.passkey-verify');

// Broadcasting authentication (required for private channels)
Broadcast::routes(['middleware' => ['web', 'auth']]);

// Signup
Route::get('/signup', [AuthController::class, 'showSignupForm'])->name('signup');
Route::post('/signup', [AuthController::class, 'signup']);
Route::post('/signup/email-lookup', SignupEmailLookupController::class)->name('signup.email-lookup');
Route::post('/signup/send-otp', [SignupOtpController::class, 'store'])->name('signup.send-otp');

// Password reset (public)
Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'store'])->name('password.email');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');

// Legal pages (public)
Route::get('/terms-of-service', fn () => Inertia::render('terms-of-service'))->name('terms-of-service');
Route::get('/privacy-policy', fn () => Inertia::render('privacy-policy'))->name('privacy-policy');

// Faculty verification (authenticated but not yet verified)
Route::middleware(['auth'])->group(function (): void {
    Route::get('/faculty-verify', [FacultyVerificationController::class, 'showForm'])->name('faculty-verify');
    Route::post('/faculty-verify', [FacultyVerificationController::class, 'verify']);
});
