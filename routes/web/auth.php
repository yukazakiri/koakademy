<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FacultyVerificationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\SignupEmailLookupController;
use App\Http\Controllers\SignupOtpController;
use App\Http\Controllers\SocialAuthController;
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

        if ($user instanceof App\Models\User) {
            return redirect($user->homeUrl());
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

    if ($user instanceof App\Models\User) {
        return redirect($user->homeUrl());
    }

    return redirect()->route('faculty.dashboard');
})->name('dashboard.redirect');

// Guest-only authentication flows
Route::middleware('guest')->group(function (): void {
    // Login
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

    // Passwordless Magic Link request
    Route::get('/magic-link', [App\Http\Controllers\MagicLinkAuthController::class, 'show'])->name('magic-link.request');
    Route::post('/magic-link/send', [App\Http\Controllers\MagicLinkAuthController::class, 'send'])->middleware('throttle:magic-link')->name('magic-link.send');

    // Two Factor Challenge
    Route::get('/two-factor-challenge', [App\Http\Controllers\TwoFactorChallengeController::class, 'create'])->name('two-factor.login');
    Route::post('/two-factor-challenge', [App\Http\Controllers\TwoFactorChallengeController::class, 'store'])->middleware('throttle:two-factor');
    Route::post('/two-factor-challenge/send-email', [App\Http\Controllers\TwoFactorChallengeController::class, 'sendEmailCode'])->middleware('throttle:two-factor-email')->name('two-factor.send-email');
    Route::post('/two-factor-challenge/passkey-options', [App\Http\Controllers\TwoFactorChallengeController::class, 'passkeyOptions'])->middleware('throttle:two-factor')->name('two-factor.passkey-options');
    Route::post('/two-factor-challenge/passkey-verify', [App\Http\Controllers\TwoFactorChallengeController::class, 'passkeyVerify'])->middleware('throttle:two-factor')->name('two-factor.passkey-verify');

    // Signup
    Route::get('/signup', [AuthController::class, 'showSignupForm'])->name('signup');
    Route::post('/signup', [AuthController::class, 'signup'])->middleware('throttle:signup-attempt');
    Route::post('/signup/cancel-social', [AuthController::class, 'cancelSocialSignup'])->name('signup.cancel-social');
    Route::post('/signup/email-lookup', SignupEmailLookupController::class)->middleware('throttle:signup-email-lookup')->name('signup.email-lookup');
    Route::post('/signup/send-otp', [SignupOtpController::class, 'store'])->middleware('throttle:signup-send-otp')->name('signup.send-otp');

    // Password reset
    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'store'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:password-reset')->name('password.update');
});

// Magic link verification (accessible even if an older session cookie exists)
Route::get('/magic-link/verify/{user}', [App\Http\Controllers\MagicLinkAuthController::class, 'verify'])->middleware('throttle:10,1')->name('magic-link.verify');
Route::post('/magic-link/verify/{user}', [App\Http\Controllers\MagicLinkAuthController::class, 'consume'])->middleware('throttle:10,1')->name('magic-link.consume');

Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('social.auth.redirect');
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('social.auth.callback');
Route::post('/demo-login/{role}', [AuthController::class, 'demoLogin'])->name('demo.login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::post('/onboarding/dismiss', [App\Http\Controllers\OnboardingDismissalController::class, 'store'])
    ->middleware('auth')
    ->name('onboarding.dismiss');

Route::middleware('auth')->group(function (): void {
    Route::get('/onboarding/progress', [App\Http\Controllers\OnboardingProgressController::class, 'show'])
        ->name('onboarding.progress.show');
    Route::post('/onboarding/progress', [App\Http\Controllers\OnboardingProgressController::class, 'store'])
        ->name('onboarding.progress.store');
});

// Broadcasting authentication (required for private channels)
Broadcast::routes(['middleware' => ['web', 'auth']]);

// Legal pages (public)
Route::get('/terms-of-service', fn () => Inertia::render('terms-of-service'))->name('terms-of-service');
Route::get('/privacy-policy', fn () => Inertia::render('privacy-policy'))->name('privacy-policy');

// Faculty verification (authenticated faculty not yet verified)
Route::middleware(['auth', 'faculty.only'])->group(function (): void {
    Route::get('/faculty-verify', [FacultyVerificationController::class, 'showForm'])->name('faculty-verify');
    Route::post('/faculty-verify', [FacultyVerificationController::class, 'verify'])->middleware('throttle:10,1');
});
