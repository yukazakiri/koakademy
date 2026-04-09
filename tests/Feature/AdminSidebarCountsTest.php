<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\GeneralSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\actingAs;

it('shares admin sidebar counts', function (): void {
    Cache::flush();

    $user = User::factory()->create(['role' => UserRole::Admin]);
    actingAs($user);

    $settingsService = app(GeneralSettingsService::class);
    $schoolYear = $settingsService->getCurrentSchoolYearString();
    $semester = $settingsService->getCurrentSemester();

    Student::factory()->count(3)->create();
    Faculty::factory()->count(2)->create();
    User::factory()->count(4)->create();

    StudentEnrollment::factory()->count(5)->create([
        'school_year' => $schoolYear,
        'semester' => $semester,
    ]);

    StudentEnrollment::factory()->count(2)->create([
        'school_year' => '1999 - 2000',
        'semester' => 2,
    ]);

    $request = Request::create('/administrators/dashboard');
    $request->setUserResolver(static fn (): User => $user);

    $shared = app(HandleInertiaRequests::class)->share($request);

    expect($shared['adminSidebarCounts'])->toBe([
        'students' => Student::query()->count(),
        'enrollments' => StudentEnrollment::query()
            ->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->count(),
        'faculties' => Faculty::query()->count(),
        'users' => User::query()->count(),
    ]);
});
