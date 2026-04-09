<?php

declare(strict_types=1);

use App\Filament\Resources\StudentEnrollments\Api\StudentEnrollmentController;
use App\Http\Controllers\Api\ActiveJobsController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\V1\ClassEnrollmentController;
use App\Http\Controllers\Api\V1\ClassPostController;
use App\Http\Controllers\Api\V1\GeneralSettingController;
use App\Http\Controllers\Api\V1\StudentVerificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::prefix('public')->name('public.')->group(function (): void {
        Route::get('/settings', [GeneralSettingController::class, 'publicWebsiteSettings'])->name('settings');
    });
});

// General Settings API Routes
Route::middleware(['auth:sanctum'])->prefix('settings')->name('api.settings.')->group(function (): void {
    // Standard CRUD operations
    Route::get('/', [GeneralSettingController::class, 'index'])->name('index');
    Route::post('/', [GeneralSettingController::class, 'store'])->name('store');
    Route::get('/{id}', [GeneralSettingController::class, 'show'])->whereNumber('id')->name('show');
    Route::put('/{id}', [GeneralSettingController::class, 'update'])->whereNumber('id')->name('update');
    Route::patch('/{id}', [GeneralSettingController::class, 'update'])->whereNumber('id')->name('patch');
    Route::delete('/{id}', [GeneralSettingController::class, 'destroy'])->whereNumber('id')->name('destroy');

    // Soft delete operations
    Route::post('/{id}/restore', [GeneralSettingController::class, 'restore'])->whereNumber('id')->name('restore');
    Route::delete('/{id}/force', [GeneralSettingController::class, 'forceDestroy'])->whereNumber('id')->name('force-destroy');

    // Additional endpoints
    Route::get('/current', [GeneralSettingController::class, 'current'])->name('current');
    Route::get('/key/{key}', [GeneralSettingController::class, 'getSetting'])->name('get-setting');
    Route::get('/service', [GeneralSettingController::class, 'serviceSettings'])->name('service-settings');

    // Service-based endpoints that utilize GeneralSettingsService
    Route::get('/user/preferences', [GeneralSettingController::class, 'userPreferences'])->name('user-preferences');
    Route::get('/global', [GeneralSettingController::class, 'globalSettings'])->name('global-settings');
    Route::post('/user/semester', [GeneralSettingController::class, 'updateUserSemester'])->name('update-user-semester');
    Route::post('/user/school-year', [GeneralSettingController::class, 'updateUserSchoolYear'])->name('update-user-school-year');
    Route::patch('/user/preferences', [GeneralSettingController::class, 'updateUserPreferences'])->name('update-user-preferences');
});

// Student Enrollment API Routes
Route::middleware(['auth:sanctum'])->prefix('enrollments')->name('api.enrollments.')->group(function (): void {
    // Standard CRUD operations
    Route::get('/', [StudentEnrollmentController::class, 'index'])->name('index');
    Route::post('/', [StudentEnrollmentController::class, 'store'])->name('store');
    Route::get('/{id}', [StudentEnrollmentController::class, 'show'])->name('show');
    Route::put('/{id}', [StudentEnrollmentController::class, 'update'])->name('update');
    Route::patch('/{id}', [StudentEnrollmentController::class, 'update'])->name('patch');
    Route::delete('/{id}', [StudentEnrollmentController::class, 'destroy'])->name('destroy');

    // Soft delete operations
    Route::post('/{id}/restore', [StudentEnrollmentController::class, 'restore'])->name('restore');
    Route::delete('/{id}/force', [StudentEnrollmentController::class, 'forceDestroy'])->name('force-destroy');

    // Additional endpoints
    Route::get('/statistics/summary', [StudentEnrollmentController::class, 'statistics'])->name('statistics');
    Route::get('/{id}/schedule', [StudentEnrollmentController::class, 'schedule'])->name('schedule');
    Route::get('/{id}/assessment', [StudentEnrollmentController::class, 'assessment'])->name('assessment');
});

// Class Enrollment API Routes
Route::middleware(['auth:sanctum'])->prefix('class-enrollments')->name('api.class-enrollments.')->group(function (): void {
    // Standard CRUD operations
    Route::get('/', [ClassEnrollmentController::class, 'index'])->name('index');
    Route::post('/', [ClassEnrollmentController::class, 'store'])->name('store');
    Route::get('/{id}', [ClassEnrollmentController::class, 'show'])->name('show');
    Route::put('/{id}', [ClassEnrollmentController::class, 'update'])->name('update');
    Route::patch('/{id}', [ClassEnrollmentController::class, 'update'])->name('patch');
    Route::delete('/{id}', [ClassEnrollmentController::class, 'destroy'])->name('destroy');

    // Soft delete operations
    Route::post('/{id}/restore', [ClassEnrollmentController::class, 'restore'])->name('restore');
    Route::delete('/{id}/force', [ClassEnrollmentController::class, 'forceDestroy'])->name('force-destroy');

    // Grade management endpoints
    Route::patch('/{id}/grades', [ClassEnrollmentController::class, 'updateGrades'])->name('update-grades');
    Route::post('/{id}/finalize', [ClassEnrollmentController::class, 'finalizeGrades'])->name('finalize-grades');
    Route::post('/{id}/verify', [ClassEnrollmentController::class, 'verifyGrades'])->name('verify-grades');

    // Additional endpoints
    Route::get('/class/{classId}', [ClassEnrollmentController::class, 'byClass'])->name('by-class');
    Route::get('/class/{classId}/statistics', [ClassEnrollmentController::class, 'gradeStatistics'])->name('grade-statistics');
    Route::patch('/class/{classId}/bulk-grades', [ClassEnrollmentController::class, 'bulkUpdateGrades'])->name('bulk-update-grades');
});

// Class Posts API Routes
Route::middleware(['auth:sanctum'])->prefix('class-posts')->name('api.class-posts.')->group(function (): void {
    // Standard CRUD operations
    Route::get('/', [ClassPostController::class, 'index'])->name('index');
    Route::post('/', [ClassPostController::class, 'store'])->name('store');
    Route::get('/{id}', [ClassPostController::class, 'show'])->name('show');
    Route::put('/{id}', [ClassPostController::class, 'update'])->name('update');
    Route::patch('/{id}', [ClassPostController::class, 'update'])->name('patch');
    Route::delete('/{id}', [ClassPostController::class, 'destroy'])->name('destroy');

    // Soft delete operations
    Route::post('/{id}/restore', [ClassPostController::class, 'restore'])->name('restore');
    Route::delete('/{id}/force', [ClassPostController::class, 'forceDestroy'])->name('force-destroy');

    // Additional endpoints
    Route::get('/class/{classId}', [ClassPostController::class, 'byClass'])->name('by-class');
    Route::post('/{id}/attachments', [ClassPostController::class, 'uploadAttachment'])->name('upload-attachment');
    Route::delete('/{id}/attachments/{attachmentIndex}', [ClassPostController::class, 'deleteAttachment'])->name('delete-attachment');
});

// Active Jobs API Routes (for real-time job tracking)
Route::middleware(['web', 'auth'])->prefix('jobs')->name('api.jobs.')->group(function (): void {
    Route::get('/', [ActiveJobsController::class, 'index'])->name('index');
    Route::get('/{jobId}', [ActiveJobsController::class, 'show'])->name('show');
    Route::delete('/{jobId}', [ActiveJobsController::class, 'dismiss'])->name('dismiss');
});

// Organization API Routes (Multi-tenancy)
Route::middleware(['web', 'auth'])->prefix('organizations')->name('api.organizations.')->group(function (): void {
    Route::get('/', [OrganizationController::class, 'index'])->name('index');
    Route::get('/current', [OrganizationController::class, 'current'])->name('current');
    Route::post('/switch', [OrganizationController::class, 'switch'])->name('switch');
    Route::post('/', [OrganizationController::class, 'store'])->name('store');
    Route::put('/{id}', [OrganizationController::class, 'update'])->whereNumber('id')->name('update');
    Route::delete('/context', [OrganizationController::class, 'clear'])->name('clear-context');
});

// Student Verification API Routes
Route::middleware(['auth:sanctum'])->prefix('students')->name('api.students.')->group(function (): void {
    Route::post('/verify', [StudentVerificationController::class, 'verify'])->name('verify');
});
