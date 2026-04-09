<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\GeneralSettingsService;

use function Pest\Laravel\actingAs;

it('saves semester to user_settings when updated via administrators route', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    actingAs($user)
        ->put(route('administrators.settings.semester.update'), ['semester' => 1])
        ->assertRedirect();

    $setting = UserSetting::where('user_id', $user->id)->first();
    expect($setting)->not->toBeNull()
        ->and($setting->semester)->toBe(1);
});

it('saves school year to user_settings when updated via administrators route', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    actingAs($user)
        ->put(route('administrators.settings.school-year.update'), ['school_year_start' => 2025])
        ->assertRedirect();

    $setting = UserSetting::where('user_id', $user->id)->first();
    expect($setting)->not->toBeNull()
        ->and($setting->school_year_start)->toBe(2025);
});

it('GeneralSettingsService reads user semester after auth is established', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    UserSetting::updateOrCreate(
        ['user_id' => $user->id],
        ['semester' => 2, 'school_year_start' => 2025]
    );

    actingAs($user);

    $service = app(GeneralSettingsService::class);
    expect($service->getCurrentSemester())->toBe(2)
        ->and($service->getCurrentSchoolYearStart())->toBe(2025);
});

it('semester selector returns empty when not authenticated', function () {
    $service = app(GeneralSettingsService::class);
    // Without auth, should return defaults (not crash)
    expect($service->getCurrentSemester())->toBeInt()
        ->and($service->getAvailableSemesters())->not->toBeEmpty();
});
