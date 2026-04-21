<?php

declare(strict_types=1);

use App\Http\Controllers\ActionCenterController;
use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\ClassesController;
use App\Http\Controllers\DigitalIdCardController;
use App\Http\Controllers\FacultyClassController;
use App\Http\Controllers\FacultyClassShsStudentController;
use App\Http\Controllers\FacultyClassStudentSearchController;
use App\Http\Controllers\FacultyGlobalSearchController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StudentInfoController;
use App\Http\Controllers\UserSettingController;
use App\Support\FacultyPortalData;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Modules\Announcement\Http\Controllers\PortalAnnouncementController;

/*
|--------------------------------------------------------------------------
| Faculty Portal Routes
|--------------------------------------------------------------------------
|
| Routes for the faculty portal including dashboard, schedule, class
| management, grades, attendance, profile, and settings.
| Protected by 'auth' and 'faculty.verified' middleware.
|
*/

Route::middleware(['auth', 'faculty.verified', 'faculty.only', 'ensure.feature'])
    ->prefix('faculty')
    ->name('faculty.')
    ->group(function (): void {
        // Dashboard
        Route::get('/dashboard', function () {
            $user = Auth::user();
            $facultyData = FacultyPortalData::build($user);

            // Generate ID card data
            $idCardService = app(App\Services\DigitalIdCardService::class);
            $faculty = App\Models\Faculty::where('email', $user->email)->first();
            $idCardData = $faculty ? $idCardService->generateFacultyIdCard($faculty) : null;

            $settingsService = app(App\Services\GeneralSettingsService::class);

            return Inertia::render('faculty/dashboard', [
                'user' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $faculty ? $faculty->getFilamentAvatarUrl() : ($user->avatar_url ?? null),
                    'role' => $user->role?->getLabel() ?? 'User',
                ],
                'faculty_data' => $facultyData,
                'id_card' => $idCardData,
                'current_semester' => (string) $settingsService->getCurrentSemester(),
                'current_school_year' => $settingsService->getCurrentSchoolYearString(),
                'flash' => session('flash'),
            ]);
        })->name('dashboard');

        // Action Center
        Route::get('/action-center', [ActionCenterController::class, '__invoke'])->name('action-center');
        Route::patch('/action-center/activities/{post}/status', [ActionCenterController::class, 'updateStatus'])->name('action-center.status.update');

        // Schedule
        Route::get('/schedule', function () {
            $user = Auth::user();
            $facultyData = FacultyPortalData::build($user);

            return Inertia::render('faculty/schedule/index', [
                'user' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar_url ?? null,
                    'role' => $user->role?->getLabel() ?? 'User',
                ],
                'faculty_data' => $facultyData,
                'flash' => session('flash'),
            ]);
        })->name('schedule');

        // Classes
        Route::get('/classes', [ClassesController::class, 'index'])->name('classes.index');
        Route::get('/classes/strand-subjects', [ClassesController::class, 'getStrandSubjects'])->name('classes.strand-subjects');
        Route::post('/classes/create', [ClassesController::class, 'store'])->name('classes.create');
        Route::get('/classes/{class}', [FacultyClassController::class, 'show'])->name('classes.show');
        Route::get('/classes/{class}/quick-action-data', [FacultyClassController::class, 'getQuickActionData'])->name('classes.quick-action-data');
        Route::put('/classes/{class}', [ClassesController::class, 'update'])->name('classes.update');
        Route::put('/classes/{class}/schedules', [FacultyClassController::class, 'updateSchedules'])->name('classes.schedules.update');

        // Class Settings
        Route::put('/classes/{class}/settings', [FacultyClassController::class, 'updateSettings'])->name('classes.settings.update');

        // Class Activity Log
        Route::get('/classes/{class}/activity-log', [ClassesController::class, 'activityLog'])->name('classes.activity-log');

        // Grades
        Route::put('/classes/{class}/grades', [FacultyClassController::class, 'updateGrades'])->name('classes.grades.update');
        Route::post('/classes/{class}/grades/submit', [FacultyClassController::class, 'submitGrades'])->name('classes.grades.submit');

        // Class Posts
        Route::post('/classes/{class}/posts', [FacultyClassController::class, 'storePost'])->name('classes.posts.store');
        Route::put('/classes/{class}/posts/{post}', [FacultyClassController::class, 'updatePost'])->name('classes.posts.update');
        Route::delete('/classes/{class}/posts/{post}', [FacultyClassController::class, 'destroyPost'])->name('classes.posts.destroy');

        // Class Students
        Route::get('/classes/{class}/students/search', FacultyClassStudentSearchController::class)->name('classes.students.search');
        Route::get('/classes/{class}/students/export/pdf', [FacultyClassController::class, 'downloadStudentListPdf'])->name('classes.students.export.pdf');
        Route::match(['get', 'post'], '/classes/{class}/students/export', [FacultyClassController::class, 'exportStudentList'])->name('classes.students.export');
        Route::post('/classes/{class}/students', [FacultyClassController::class, 'storeStudent'])->name('classes.students.store');
        Route::post('/classes/{class}/students/create-shs', FacultyClassShsStudentController::class)->name('classes.students.store-shs');
        Route::get('/classes/{class}/shs-strands', [FacultyClassController::class, 'getShsStrands'])->name('classes.shs-strands');
        Route::delete('/classes/{class}/students/{student}', [FacultyClassController::class, 'removeStudent'])->name('classes.students.destroy');
        Route::post('/classes/{class}/students/{student}/move', [FacultyClassController::class, 'requestMove'])->name('classes.students.move.request');
        Route::get('/classes/{class}/students/{student}/move/{target_class}/accept', [FacultyClassController::class, 'acceptMove'])->name('classes.students.move.accept');
        Route::get('/classes/{class}/related-sections', [FacultyClassController::class, 'getRelatedSections'])->name('classes.sections.related');

        // Attendance
        Route::post('/classes/{class}/attendance/sessions', [FacultyClassController::class, 'storeAttendanceSession'])->name('classes.attendance.sessions.store');
        Route::put('/classes/{class}/attendance/sessions/{session}', [FacultyClassController::class, 'updateAttendanceSession'])->name('classes.attendance.sessions.update');
        Route::post('/classes/{class}/attendance/sessions/{session}/records', [FacultyClassController::class, 'updateAttendanceRecords'])->name('classes.attendance.records.update');
        Route::delete('/classes/{class}/attendance/sessions/{session}', [FacultyClassController::class, 'destroyAttendanceSession'])->name('classes.attendance.sessions.destroy');
        Route::get('/classes/{class}/attendance/export', [FacultyClassController::class, 'exportAttendance'])->name('classes.attendance.export');

        // Global Search
        Route::get('/search/classes', [FacultyGlobalSearchController::class, 'classes'])->name('faculty.search.classes');
        Route::get('/search/students', [FacultyGlobalSearchController::class, 'students'])->name('faculty.search.students');

        // Students (List)
        Route::get('/students', function () {
            // For now, redirect to global student search or classes as there is no dedicated student list page yet
            return redirect()->route('faculty.classes.index');
        })->name('students.index');

        // Students (Individual)
        Route::get('/students/{student}', [StudentInfoController::class, 'show'])->name('students.show');

        // Changelog
        Route::get('/changelog', App\Http\Controllers\ChangelogController::class)->name('changelog');

        // Profile
        Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
        Route::put('/profile', [ProfileController::class, 'updateUser'])->name('profile.update');
        Route::put('/profile/faculty', [ProfileController::class, 'updateFaculty'])->name('profile.faculty.update');
        Route::get('/profile/password', [ProfileController::class, 'showChangePassword'])->name('profile.password');
        Route::put('/profile/password', [ProfileController::class, 'changePassword'])->name('profile.password.update');

        // Two Factor Authentication & Sessions
        Route::post('/profile/two-factor-authentication/enable', [ProfileController::class, 'enableTwoFactor'])->name('profile.two-factor.enable');
        Route::post('/profile/two-factor-authentication/confirm', [ProfileController::class, 'confirmTwoFactor'])->name('profile.two-factor.confirm');
        Route::delete('/profile/two-factor-authentication', [ProfileController::class, 'disableTwoFactor'])->name('profile.two-factor.disable');
        Route::post('/profile/email-authentication', [ProfileController::class, 'toggleEmailAuthentication'])->name('profile.email-auth.toggle');
        Route::post('/profile/experimental-features', [ProfileController::class, 'toggleExperimentalFeatures'])->name('profile.experimental-features');
        Route::post('/profile/two-factor-authentication/recovery-codes', [ProfileController::class, 'regenerateRecoveryCodes'])->name('profile.two-factor.recovery-codes');
        Route::delete('/profile/other-browser-sessions', [ProfileController::class, 'logoutOtherBrowserSessions'])->name('profile.browser-sessions.logout');

        // Passkeys
        Route::post('/profile/passkeys/options', [App\Http\Controllers\PasskeyController::class, 'generateRegistrationOptions'])->name('passkeys.options');
        Route::post('/profile/passkeys', [App\Http\Controllers\PasskeyController::class, 'store'])->name('passkeys.store');
        Route::delete('/profile/passkeys/{id}', [App\Http\Controllers\PasskeyController::class, 'destroy'])->name('passkeys.destroy');
        Route::get('/profile/passkeys', [App\Http\Controllers\PasskeyController::class, 'index'])->name('passkeys.index');

        // API Keys (Developer Mode)
        Route::get('/profile/api-keys', [ApiKeyController::class, 'index'])->name('api-keys.index');
        Route::post('/profile/api-keys', [ApiKeyController::class, 'store'])->name('api-keys.store');
        Route::delete('/profile/api-keys/{id}', [ApiKeyController::class, 'destroy'])->name('api-keys.destroy');
        Route::get('/profile/api-keys/developer-mode', [ApiKeyController::class, 'checkDeveloperMode'])->name('api-keys.developer-mode');

        // Settings
        Route::put('/settings/semester', [UserSettingController::class, 'updateSemester'])->name('settings.semester.update');
        Route::put('/settings/school-year', [UserSettingController::class, 'updateSchoolYear'])->name('settings.school-year.update');
        Route::put('/settings/active-school', [UserSettingController::class, 'updateActiveSchool'])->name('settings.active-school.update');

        // Help & Support
        Route::get('/help', [App\Http\Controllers\HelpTicketController::class, 'index'])->name('help.index');
        Route::post('/help', [App\Http\Controllers\HelpTicketController::class, 'store'])->name('help.store');
        Route::get('/help/{helpTicket}', [App\Http\Controllers\HelpTicketController::class, 'show'])->name('help.show');
        Route::post('/help/{helpTicket}/reply', [App\Http\Controllers\HelpTicketController::class, 'reply'])->name('help.reply');

        // Announcements
        Route::get('/announcements', [PortalAnnouncementController::class, 'index'])->name('announcements.index');

        // Notifications
        Route::post('/notifications/{id}/read', [App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::post('/notifications/mark-all-read', [App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
        Route::delete('/notifications/{id}', [App\Http\Controllers\NotificationController::class, 'destroy'])->name('notifications.destroy');

        // Digital ID Card
        Route::get('/id-card', [DigitalIdCardController::class, 'show'])->name('id-card.show');
        Route::post('/id-card/refresh', [DigitalIdCardController::class, 'refresh'])->name('id-card.refresh');
        Route::get('/id-card/view', [DigitalIdCardController::class, 'index'])->name('id-card.index');
    });
