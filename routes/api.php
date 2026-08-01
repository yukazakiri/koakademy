<?php

declare(strict_types=1);

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| The external API is versioned under /api/v1 and consumed by mobile apps
| and third-party integrations, authenticated with Sanctum tokens (see
| /api/v1/auth/login). Unversioned legacy paths (/api/enrollments, ...)
| are kept as aliases serving identical responses from V1 controllers so
| existing clients keep working; new consumers must use /api/v1.
|
| Route definitions live in domain-scoped partials under routes/api/ and
| are mounted below. Names follow api.v1.<domain>.<action>; legacy aliases
| reuse their historical names (api.<domain>.<action>).
|
*/

// ────────────────────────────────────────────────────────────────────────
// Legacy unauthenticated entrypoint
// ────────────────────────────────────────────────────────────────────────
Route::get('/user', fn (Request $request): UserResource => UserResource::make($request->user()))
    ->middleware('auth:sanctum')
    ->name('user');

// ────────────────────────────────────────────────────────────────────────
// Canonical versioned API — /api/v1/*
// ────────────────────────────────────────────────────────────────────────
Route::prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {
        require __DIR__.'/api/public.php';
        require __DIR__.'/api/auth.php';
        require __DIR__.'/api/admin.php';
        require __DIR__.'/api/student.php';
        require __DIR__.'/api/faculty.php';

        // Internal JSON endpoints exposed to token clients (mobile).
        Route::middleware(['auth:sanctum'])->group(base_path('routes/api/internal.php'));
    });

// ────────────────────────────────────────────────────────────────────────
// Legacy unversioned aliases — identical responses, historical names.
// New integrations MUST NOT use these paths.
// ────────────────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum'])->prefix('profile')->name('api.profile.')->group(function (): void {
    Route::get('/me', [App\Http\Controllers\Api\V1\ProfileController::class, 'show'])->name('me');
});

Route::middleware(['auth:sanctum'])->name('api.')->group(function (): void {
    require __DIR__.'/api/admin.php';
    require __DIR__.'/api/student.php';
    require __DIR__.'/api/faculty.php';
});

// Internal JSON endpoints used by the first-party Inertia SPA.
Route::middleware(['web', 'auth'])->name('api.')->group(base_path('routes/api/internal.php'));
