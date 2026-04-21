<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\GeneralSetting;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\GeneralSettingsService;
use App\Support\FacultyPortalData;
use Illuminate\Support\Facades\Cache;

it('reflects selected academic period in faculty dashboard data', function (): void {
    Cache::forget('general_settings_id');

    GeneralSetting::factory()->create([
        'school_starting_date' => '2025-08-01',
        'semester' => 1,
    ]);

    Cache::forget('general_settings_id');

    $user = User::factory()->create([
        'role' => UserRole::Instructor,
        'email' => 'faculty-dashboard@example.com',
    ]);

    $faculty = Faculty::factory()->create([
        'email' => $user->email,
    ]);

    Classes::factory()->count(3)->create([
        'faculty_id' => $faculty->id,
        'school_year' => '2025 - 2026',
        'semester' => 2,
        'classification' => 'college',
    ]);

    UserSetting::query()->updateOrCreate(
        ['user_id' => $user->id],
        ['semester' => 2, 'school_year_start' => 2025],
    );

    $this->actingAs($user);
    app()->forgetInstance(GeneralSettingsService::class);

    $dashboardPayloadForSecondSemester = FacultyPortalData::build($user);

    $activeClassesStatForSecondSemester = collect($dashboardPayloadForSecondSemester['stats'])
        ->firstWhere('label', 'Active Classes');

    expect($activeClassesStatForSecondSemester)->not->toBeNull()
        ->and($activeClassesStatForSecondSemester['value'])->toBe(3)
        ->and($dashboardPayloadForSecondSemester['upcoming_classes'])->toHaveCount(3);

    UserSetting::query()->updateOrCreate(
        ['user_id' => $user->id],
        ['semester' => 1, 'school_year_start' => 2025],
    );

    app()->forgetInstance(GeneralSettingsService::class);

    $dashboardPayloadForFirstSemester = FacultyPortalData::build($user);

    $activeClassesStatForFirstSemester = collect($dashboardPayloadForFirstSemester['stats'])
        ->firstWhere('label', 'Active Classes');

    expect($activeClassesStatForFirstSemester)->not->toBeNull()
        ->and($activeClassesStatForFirstSemester['value'])->toBe(0)
        ->and($dashboardPayloadForFirstSemester['upcoming_classes'])->toHaveCount(0);
});
