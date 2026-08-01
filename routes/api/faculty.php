<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Faculty\FacultyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 - Faculty Mobile App
|--------------------------------------------------------------------------
|
| Endpoints consumed by the faculty mobile application. All routes are
| protected by auth:sanctum.
|
*/

Route::middleware(['auth:sanctum'])->prefix('faculty')->name('faculty.')->group(function (): void {
    Route::get('/profile', [FacultyController::class, 'profile'])->name('profile');
    Route::put('/profile', [FacultyController::class, 'updateProfile'])->name('update-profile');

    Route::get('/classes', [FacultyController::class, 'classes'])->name('classes');
    Route::get('/classes/{classId}', [FacultyController::class, 'classDetails'])->name('class-details');
    Route::get('/classes/{classId}/students', [FacultyController::class, 'classStudents'])->name('class-students');

    Route::get('/schedules', [FacultyController::class, 'schedules'])->name('schedules');

    Route::get('/students', [FacultyController::class, 'students'])->name('students');

    // Attendance
    Route::get('/classes/{classId}/attendance/sessions', [FacultyController::class, 'attendanceSessions'])->name('attendance-sessions');
    Route::post('/classes/{classId}/attendance/sessions', [FacultyController::class, 'storeAttendanceSession'])->name('store-attendance-session');
    Route::put('/classes/{classId}/attendance/sessions/{sessionId}', [FacultyController::class, 'updateAttendanceSession'])->name('update-attendance-session');
    Route::post('/classes/{classId}/attendance/sessions/{sessionId}/records', [FacultyController::class, 'updateAttendanceRecords'])->name('update-attendance-records');

    // Grades
    Route::patch('/classes/{classId}/enrollments/{enrollmentId}/grades', [FacultyController::class, 'updateGrades'])->name('update-grades');
});
