<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ClassEnrollmentController;
use App\Http\Controllers\Api\V1\ClassPostController;
use App\Http\Controllers\Api\V1\GeneralSettingController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\StudentEnrollmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 - Staff & Administration
|--------------------------------------------------------------------------
|
| Authenticated CRUD for staff-facing data: general settings, student
| enrollments, class enrollments (grades) and class posts. All routes are
| protected by auth:sanctum.
|
*/

Route::middleware(['auth:sanctum'])->group(function (): void {

    // Authenticated user profile
    Route::prefix('profile')->name('profile.')->group(function (): void {
        Route::get('/me', [ProfileController::class, 'show'])->name('me');
    });

    // General Settings
    Route::prefix('settings')->name('settings.')->group(function (): void {
        Route::get('/', [GeneralSettingController::class, 'index'])->name('index');
        Route::post('/', [GeneralSettingController::class, 'store'])->name('store');
        Route::get('/current', [GeneralSettingController::class, 'current'])->name('current');
        Route::get('/global', [GeneralSettingController::class, 'globalSettings'])->name('global-settings');
        Route::get('/service', [GeneralSettingController::class, 'serviceSettings'])->name('service-settings');
        Route::get('/key/{key}', [GeneralSettingController::class, 'getSetting'])->name('get-setting');
        Route::get('/user/preferences', [GeneralSettingController::class, 'userPreferences'])->name('user-preferences');
        Route::post('/user/semester', [GeneralSettingController::class, 'updateUserSemester'])->name('update-user-semester');
        Route::post('/user/school-year', [GeneralSettingController::class, 'updateUserSchoolYear'])->name('update-user-school-year');
        Route::patch('/user/preferences', [GeneralSettingController::class, 'updateUserPreferences'])->name('update-user-preferences');

        Route::get('/{id}', [GeneralSettingController::class, 'show'])->whereNumber('id')->name('show');
        Route::put('/{id}', [GeneralSettingController::class, 'update'])->whereNumber('id')->name('update');
        Route::patch('/{id}', [GeneralSettingController::class, 'update'])->whereNumber('id')->name('patch');
        Route::delete('/{id}', [GeneralSettingController::class, 'destroy'])->whereNumber('id')->name('destroy');

        // Soft delete operations
        Route::post('/{id}/restore', [GeneralSettingController::class, 'restore'])->whereNumber('id')->name('restore');
        Route::delete('/{id}/force', [GeneralSettingController::class, 'forceDestroy'])->whereNumber('id')->name('force-destroy');
    });

    // Student Enrollments
    Route::prefix('enrollments')->name('enrollments.')->group(function (): void {
        Route::get('/', [StudentEnrollmentController::class, 'index'])->name('index');
        Route::post('/', [StudentEnrollmentController::class, 'store'])->name('store');
        Route::get('/statistics/summary', [StudentEnrollmentController::class, 'statistics'])->name('statistics');
        Route::get('/{id}', [StudentEnrollmentController::class, 'show'])->whereNumber('id')->name('show');
        Route::put('/{id}', [StudentEnrollmentController::class, 'update'])->whereNumber('id')->name('update');
        Route::patch('/{id}', [StudentEnrollmentController::class, 'update'])->whereNumber('id')->name('patch');
        Route::delete('/{id}', [StudentEnrollmentController::class, 'destroy'])->whereNumber('id')->name('destroy');

        // Workflow operations
        Route::post('/{id}/transitions', [StudentEnrollmentController::class, 'transition'])->whereNumber('id')->name('transitions.store');
        Route::post('/{id}/reopen', [StudentEnrollmentController::class, 'reopen'])->whereNumber('id')->name('reopen');

        // Soft delete operations
        Route::post('/{id}/restore', [StudentEnrollmentController::class, 'restore'])->whereNumber('id')->name('restore');
        Route::delete('/{id}/force', [StudentEnrollmentController::class, 'forceDestroy'])->whereNumber('id')->name('force-destroy');

        // Additional endpoints
        Route::get('/{id}/schedule', [StudentEnrollmentController::class, 'schedule'])->whereNumber('id')->name('schedule');
        Route::get('/{id}/assessment', [StudentEnrollmentController::class, 'assessment'])->whereNumber('id')->name('assessment');
    });

    // Class Enrollments (grades)
    Route::prefix('class-enrollments')->name('class-enrollments.')->group(function (): void {
        Route::get('/', [ClassEnrollmentController::class, 'index'])->name('index');
        Route::post('/', [ClassEnrollmentController::class, 'store'])->name('store');

        Route::get('/class/{classId}', [ClassEnrollmentController::class, 'byClass'])->name('by-class');
        Route::get('/class/{classId}/statistics', [ClassEnrollmentController::class, 'gradeStatistics'])->name('grade-statistics');
        Route::patch('/class/{classId}/bulk-grades', [ClassEnrollmentController::class, 'bulkUpdateGrades'])->name('bulk-update-grades');

        Route::get('/{id}', [ClassEnrollmentController::class, 'show'])->whereNumber('id')->name('show');
        Route::put('/{id}', [ClassEnrollmentController::class, 'update'])->whereNumber('id')->name('update');
        Route::patch('/{id}', [ClassEnrollmentController::class, 'update'])->whereNumber('id')->name('patch');
        Route::delete('/{id}', [ClassEnrollmentController::class, 'destroy'])->whereNumber('id')->name('destroy');

        // Grade management
        Route::patch('/{id}/grades', [ClassEnrollmentController::class, 'updateGrades'])->whereNumber('id')->name('update-grades');
        Route::post('/{id}/finalize', [ClassEnrollmentController::class, 'finalizeGrades'])->whereNumber('id')->name('finalize-grades');
        Route::post('/{id}/verify', [ClassEnrollmentController::class, 'verifyGrades'])->whereNumber('id')->name('verify-grades');

        // Soft delete operations
        Route::post('/{id}/restore', [ClassEnrollmentController::class, 'restore'])->whereNumber('id')->name('restore');
        Route::delete('/{id}/force', [ClassEnrollmentController::class, 'forceDestroy'])->whereNumber('id')->name('force-destroy');
    });

    // Class Posts
    Route::prefix('class-posts')->name('class-posts.')->group(function (): void {
        Route::get('/', [ClassPostController::class, 'index'])->name('index');
        Route::post('/', [ClassPostController::class, 'store'])->name('store');
        Route::get('/class/{classId}', [ClassPostController::class, 'byClass'])->name('by-class');

        Route::get('/{id}', [ClassPostController::class, 'show'])->whereNumber('id')->name('show');
        Route::put('/{id}', [ClassPostController::class, 'update'])->whereNumber('id')->name('update');
        Route::patch('/{id}', [ClassPostController::class, 'update'])->whereNumber('id')->name('patch');
        Route::delete('/{id}', [ClassPostController::class, 'destroy'])->whereNumber('id')->name('destroy');

        // Attachments
        Route::post('/{id}/attachments', [ClassPostController::class, 'uploadAttachment'])->whereNumber('id')->name('upload-attachment');
        Route::delete('/{id}/attachments/{attachmentIndex}', [ClassPostController::class, 'deleteAttachment'])
            ->whereNumber('id')
            ->whereNumber('attachmentIndex')
            ->name('delete-attachment');

        // Soft delete operations
        Route::post('/{id}/restore', [ClassPostController::class, 'restore'])->whereNumber('id')->name('restore');
        Route::delete('/{id}/force', [ClassPostController::class, 'forceDestroy'])->whereNumber('id')->name('force-destroy');
    });
});
