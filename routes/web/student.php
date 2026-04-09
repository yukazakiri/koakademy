<?php

declare(strict_types=1);

use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\DigitalIdCardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StudentDashboardController;
use App\Http\Controllers\StudentGlobalSearchController;
use App\Http\Controllers\UserSettingController;
use Illuminate\Support\Facades\Route;
use Modules\Announcement\Http\Controllers\PortalAnnouncementController;

/*
|--------------------------------------------------------------------------
| Student Portal Routes
|--------------------------------------------------------------------------
|
| Routes for authenticated students.
|
*/

Route::middleware(['auth', 'student.only', 'ensure.feature'])->prefix('student')->name('student.')->group(function (): void {
    Route::get('/dashboard', StudentDashboardController::class)->name('dashboard');

    // Global Search
    Route::get('/search', StudentGlobalSearchController::class)->name('search');

    // Settings (Semester/School Year Selector)
    Route::put('/settings/semester', [UserSettingController::class, 'updateSemester'])->name('settings.semester.update');
    Route::put('/settings/school-year', [UserSettingController::class, 'updateSchoolYear'])->name('settings.school-year.update');
    Route::put('/settings/active-school', [UserSettingController::class, 'updateActiveSchool'])->name('settings.active-school.update');

    // Profile
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'updateUser'])->name('profile.update');
    Route::put('/profile/student', [ProfileController::class, 'updateStudent'])->name('profile.student.update');
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

    // Help & Support
    Route::get('/help', [App\Http\Controllers\HelpTicketController::class, 'index'])->name('help.index');
    Route::post('/help', [App\Http\Controllers\HelpTicketController::class, 'store'])->name('help.store');
    Route::get('/help/{helpTicket}', [App\Http\Controllers\HelpTicketController::class, 'show'])->name('help.show');
    Route::post('/help/{helpTicket}/reply', [App\Http\Controllers\HelpTicketController::class, 'reply'])->name('help.reply');

    // Classes / Enrollment
    Route::get('/classes', App\Http\Controllers\StudentClassesController::class)->name('classes.index');
    Route::get('/classes/{class}', [App\Http\Controllers\StudentClassController::class, 'show'])->name('classes.show');
    Route::get('/schedule', App\Http\Controllers\StudentScheduleController::class)->name('schedule');
    Route::get('/tuition', [App\Http\Controllers\StudentTuitionController::class, 'index'])->name('tuition.index');
    Route::get('/tuition/soa', [App\Http\Controllers\StudentTuitionController::class, 'soa'])->name('tuition.soa');

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
