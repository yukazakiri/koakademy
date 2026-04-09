<?php

declare(strict_types=1);

use App\Http\Controllers\ClassesController;
use App\Http\Controllers\DigitalIdCardController;
use App\Models\Faculty;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;

Route::passkeys();

/*
|--------------------------------------------------------------------------
| Admin Domain Routes
|--------------------------------------------------------------------------
*/
require __DIR__.'/web/admin.php';

/*
|--------------------------------------------------------------------------
| Passkey Authentication Routes (Global - not domain-specific)
|--------------------------------------------------------------------------
*/
Route::post('/passkeys/options', [App\Http\Controllers\PasskeyAuthController::class, 'generateAuthenticationOptions'])->name('passkeys.login.options');
Route::post('/passkeys/login', [App\Http\Controllers\PasskeyAuthController::class, 'verifyAuthentication'])->name('passkeys.login.verify');

/*
|--------------------------------------------------------------------------
| Portal Domain Routes
|--------------------------------------------------------------------------
*/
Route::domain(config('app.portal_host', 'portal.koakademy.test'))->group(function () {

    // Helper function for building faculty portal data (legacy support)
    if (! function_exists('build_faculty_portal_data')) {
        /**
         * @return array{
         *     stats: array,
         *     upcoming_classes: array,
         *     recent_activity: array,
         *     announcements: array,
         *     weekly_schedule: array,
         *     today_schedule: array{day: string, entries: array}
         * }
         */
        function build_faculty_portal_data($user): array
        {
            $currentDayName = Carbon::now()
                ->timezone(config('app.timezone'))
                ->format('l');

            $defaults = [
                'stats' => [],
                'upcoming_classes' => [],
                'recent_activity' => [],
                'announcements' => [],
                'weekly_schedule' => [],
                'today_schedule' => [
                    'day' => $currentDayName,
                    'entries' => [],
                ],
            ];

            if (! $user || ! method_exists($user, 'isFaculty') || ! $user->isFaculty()) {
                return $defaults;
            }

            $faculty = Faculty::where('email', $user->email)->first();

            if (! $faculty) {
                return $defaults;
            }

            $activeClassesCount = $faculty->classes()->currentAcademicPeriod()->count();

            $totalStudentsCount = App\Models\ClassEnrollment::whereIn(
                'class_id',
                $faculty->classes()->currentAcademicPeriod()->pluck('id')
            )
                ->distinct('student_id')
                ->count();

            $classesQuery = $faculty->classes()
                ->currentAcademicPeriod()
                ->with([
                    'subject',
                    'SubjectByCodeFallback',
                    'ShsSubject',
                    'Room',
                    'schedules.room',
                ]);

            $upcomingClasses = $classesQuery
                ->clone()
                ->limit(5)
                ->get()
                ->map(function ($class) {
                    $firstSubject = $class->subjects->first();

                    if (! $firstSubject) {
                        $firstSubject = $class->isShs() ? $class->ShsSubject : ($class->subject ?: $class->SubjectByCodeFallback);
                    }

                    return [
                        'id' => $class->id,
                        'subject_code' => $firstSubject?->code ?? $class->subject_code ?? 'N/A',
                        'subject_title' => $firstSubject?->title ?? 'N/A',
                        'section' => $class->section ?? 'N/A',
                        'school_year' => $class->school_year ?? 'N/A',
                        'semester' => $class->semester ?? 'N/A',
                        'room' => $class->Room?->name ?? 'TBA',
                        'students_count' => $class->class_enrollments_count ?? 0,
                    ];
                })
                ->values()
                ->all();

            $weeklyEntriesByDay = $classesQuery
                ->clone()
                ->get()
                ->flatMap(function ($class) {
                    $firstSubject = $class->subjects->first();

                    if (! $firstSubject) {
                        $firstSubject = $class->isShs() ? $class->ShsSubject : ($class->subject ?: $class->SubjectByCodeFallback);
                    }

                    return $class->schedules->map(function ($schedule) use ($class, $firstSubject) {
                        $day = ucfirst(mb_strtolower((string) $schedule->day_of_week ?? ''));

                        return [
                            'id' => $schedule->id,
                            'day' => $day,
                            'start_time' => $schedule->formatted_start_time,
                            'end_time' => $schedule->formatted_end_time,
                            'start_time_24h' => $schedule->start_time?->format('H:i'),
                            'end_time_24h' => $schedule->end_time?->format('H:i'),
                            'subject_code' => $firstSubject?->code ?? $class->subject_code ?? 'N/A',
                            'subject_title' => $firstSubject?->title ?? 'N/A',
                            'section' => $class->section ?? 'N/A',
                            'room' => $schedule->room?->name ?? $class->Room?->name ?? 'TBA',
                            'course_codes' => $class->formatted_course_codes ?? 'N/A',
                            'classification' => $class->classification ?? 'college',
                        ];
                    });
                })
                ->filter(fn ($entry) => in_array($entry['day'], ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'], true))
                ->groupBy('day')
                ->map(function ($entries) {
                    return $entries
                        ->sortBy('start_time_24h')
                        ->values();
                });

            $dayOrder = collect(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']);

            $weeklySchedule = $dayOrder
                ->map(function ($day) use ($weeklyEntriesByDay) {
                    return [
                        'day' => $day,
                        'entries' => $weeklyEntriesByDay->get($day, collect())->values()->all(),
                    ];
                })
                ->values()
                ->all();

            $todaySchedule = [
                'day' => $currentDayName,
                'entries' => $weeklyEntriesByDay->get($currentDayName, collect())->values()->all(),
            ];

            $stats = [
                [
                    'label' => 'Active Classes',
                    'value' => $activeClassesCount,
                    'icon' => 'book',
                    'trend' => '+2',
                    'trendDirection' => 'up',
                ],
                [
                    'label' => 'Total Students',
                    'value' => $totalStudentsCount,
                    'icon' => 'users',
                    'trend' => '+5%',
                    'trendDirection' => 'up',
                ],
                [
                    'label' => 'Total Hours',
                    'value' => '24',
                    'icon' => 'clock',
                    'trend' => '0%',
                    'trendDirection' => 'neutral',
                ],
                [
                    'label' => 'Performance',
                    'value' => '98%',
                    'icon' => 'activity',
                    'trend' => '+1%',
                    'trendDirection' => 'up',
                ],
            ];

            $recentActivity = [
                ['action' => 'Posted announcement', 'target' => 'Midterm Exam Schedule', 'time' => '2 hours ago'],
                ['action' => 'Updated grades', 'target' => 'CS101 - Intro to CS', 'time' => '5 hours ago'],
                ['action' => 'Added resource', 'target' => 'Lecture 5 Slides', 'time' => '1 day ago'],
                ['action' => 'Attendance taken', 'target' => 'CS102 - Data Structures', 'time' => '1 day ago'],
            ];

            $announcements = [
                ['title' => 'System Maintenance', 'content' => 'Scheduled maintenance on Saturday.', 'date' => 'Dec 10', 'type' => 'warning'],
                ['title' => 'Faculty Meeting', 'content' => 'Monthly meeting at 2 PM.', 'date' => 'Dec 12', 'type' => 'info'],
                ['title' => 'Grade Submission Deadline', 'content' => 'Final grades due by Dec 20.', 'date' => 'Dec 15', 'type' => 'important'],
            ];

            return [
                'stats' => $stats,
                'upcoming_classes' => $upcomingClasses,
                'recent_activity' => $recentActivity,
                'announcements' => $announcements,
                'weekly_schedule' => $weeklySchedule,
                'today_schedule' => $todaySchedule,
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication Routes
    |--------------------------------------------------------------------------
    */
    require __DIR__.'/web/auth.php';

    /*
    |--------------------------------------------------------------------------
    | Public Enrollment Routes
    |--------------------------------------------------------------------------
    */
    require __DIR__.'/web/enrollment.php';

    /*
    |--------------------------------------------------------------------------
    | Administrator Portal Routes
    |--------------------------------------------------------------------------
    */
    require __DIR__.'/web/administrators.php';

    /*
    |--------------------------------------------------------------------------
    | Faculty Portal Routes
    |--------------------------------------------------------------------------
    */
    require __DIR__.'/web/faculty-portal.php';

    /*
    |--------------------------------------------------------------------------
    | Student Portal Routes
    |--------------------------------------------------------------------------
    */
    require __DIR__.'/web/student.php';

    /*
    |--------------------------------------------------------------------------
    | Download Routes
    |--------------------------------------------------------------------------
    */
    require __DIR__.'/web/downloads.php';

    /*
    |--------------------------------------------------------------------------
    | Testing Routes
    |--------------------------------------------------------------------------
    */
    require __DIR__.'/web/testing.php';
});

/*
|--------------------------------------------------------------------------
| Global Routes (not domain-specific)
|--------------------------------------------------------------------------
*/
require __DIR__.'/web/setup.php';

Route::post('/classes/validate-schedules', [ClassesController::class, 'validateSchedules']);

// Public ID Card Verification (accessible without authentication)
Route::get('/id-card/verify/{token}', [DigitalIdCardController::class, 'verify'])->name('id-card.verify');

// Public Changelog (accessible without authentication)
Route::get('/changelog', App\Http\Controllers\ChangelogController::class)->name('changelog');

Route::middleware(['auth'])->group(function () {
    // Generic Social Auth Routes
    Route::get('/integrations/{provider}/connect', [App\Http\Controllers\SocialAuthController::class, 'connect'])->name('social.connect');
    Route::get('/integrations/{provider}/callback', [App\Http\Controllers\SocialAuthController::class, 'callback'])->name('social.callback');
    Route::post('/integrations/{provider}/disconnect', [App\Http\Controllers\SocialAuthController::class, 'disconnect'])->name('social.disconnect');

    // Help & Support Redirectors
    Route::get('/help', function () {
        $user = Illuminate\Support\Facades\Auth::user();
        if ($user->canAccessAdminPortal()) {
            return redirect()->route('administrators.help-tickets.index');
        }
        $prefix = $user->isStudentRole() ? 'student' : 'faculty';

        return redirect()->route("{$prefix}.help.index");
    })->name('help.index');

    Route::get('/help/{helpTicket}', function ($helpTicket) {
        $user = Illuminate\Support\Facades\Auth::user();
        if ($user->canAccessAdminPortal()) {
            return redirect()->route('administrators.help-tickets.show', $helpTicket);
        }
        $prefix = $user->isStudentRole() ? 'student' : 'faculty';

        return redirect()->route("{$prefix}.help.show", $helpTicket);
    })->name('help.show');

    Route::post('/help', function (Illuminate\Http\Request $request) {
        $user = Illuminate\Support\Facades\Auth::user();
        $prefix = $user->isStudentRole() ? 'student' : 'faculty';

        return redirect()->route("{$prefix}.help.store", $request->all(), 307); // 307 to preserve POST
    })->name('help.store');

    Route::post('/help/{helpTicket}/reply', function (Illuminate\Http\Request $request, $helpTicket) {
        $user = Illuminate\Support\Facades\Auth::user();
        $prefix = $user->isStudentRole() ? 'student' : 'faculty';

        return redirect()->route("{$prefix}.help.reply", ['helpTicket' => $helpTicket], 307);
    })->name('help.reply');

    // Profile Redirection
    Route::get('/profile', [App\Http\Controllers\ProfileController::class, 'redirect'])->name('profile.redirect');

    // Specific Sync Routes
    Route::post('/integrations/google/sync', [App\Http\Controllers\GoogleCalendarController::class, 'sync'])->name('integrations.google.sync');
    Route::post('/integrations/google/unsync', [App\Http\Controllers\GoogleCalendarController::class, 'unsync'])->name('integrations.google.unsync');

    // Organization Routes
    Route::post('/organizations/switch', [App\Http\Controllers\Api\OrganizationController::class, 'switch'])->name('organizations.switch');
    Route::post('/organizations', [App\Http\Controllers\Api\OrganizationController::class, 'store'])->name('organizations.store');
});
