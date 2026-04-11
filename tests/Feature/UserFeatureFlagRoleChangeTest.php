<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Features\Onboarding\FacultyToolkit;
use App\Features\Onboarding\StudentClasses;
use App\Features\Onboarding\StudentSchedule;
use App\Features\Onboarding\StudentTuition;
use App\Models\OnboardingFeature;
use App\Models\User;
use App\Services\UserFeatureFlagService;
use Laravel\Pennant\Feature;

beforeEach(function (): void {
    config([
        'onboarding.experimental_feature_keys' => [
            'onboarding-faculty-toolkit',
            'onboarding-student-classes',
            'onboarding-student-schedule',
            'onboarding-student-tuition',
        ],
        'onboarding.experimental_features_roles' => [
            'onboarding-faculty-toolkit' => ['faculty'],
            'onboarding-student-classes' => ['student', 'shs_student', 'graduate_student'],
            'onboarding-student-schedule' => ['student', 'shs_student', 'graduate_student'],
            'onboarding-student-tuition' => ['student', 'shs_student', 'graduate_student'],
        ],
    ]);
});

it('returns only experimental features allowed for the selected role', function (): void {
    $options = app(UserFeatureFlagService::class)->featureOptionsForRole(UserRole::Student);

    expect(array_keys($options))
        ->toBe([
            'onboarding-student-classes',
            'onboarding-student-schedule',
            'onboarding-student-tuition',
        ])
        ->not->toContain('onboarding-faculty-toolkit');
});

it('resets stale feature overrides when a user role changes', function (): void {
    foreach ([
        'onboarding-student-classes',
        'onboarding-student-schedule',
        'onboarding-student-tuition',
    ] as $featureKey) {
        OnboardingFeature::factory()->create([
            'feature_key' => $featureKey,
            'audience' => 'student',
            'is_active' => true,
        ]);
    }

    OnboardingFeature::factory()->create([
        'feature_key' => 'onboarding-faculty-toolkit',
        'audience' => 'faculty',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'role' => UserRole::Instructor,
    ]);

    Feature::for($user)->activate(FacultyToolkit::class);
    Feature::for($user)->deactivate([
        StudentClasses::class,
        StudentSchedule::class,
        StudentTuition::class,
    ]);

    $user->update([
        'role' => UserRole::Student,
    ]);

    app(UserFeatureFlagService::class)->syncFeatureOverrides(
        $user,
        [],
        $user->role,
        resetToRoleDefaults: true,
    );

    expect(Feature::for($user)->active(StudentClasses::class))->toBeTrue()
        ->and(Feature::for($user)->active(StudentSchedule::class))->toBeTrue()
        ->and(Feature::for($user)->active(StudentTuition::class))->toBeTrue()
        ->and(Feature::for($user)->active(FacultyToolkit::class))->toBeFalse();
});

it('restores role defaults before applying selected overrides', function (): void {
    OnboardingFeature::factory()->create([
        'feature_key' => 'onboarding-student-schedule',
        'audience' => 'student',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'role' => UserRole::Student,
    ]);

    app(UserFeatureFlagService::class)->syncFeatureOverrides(
        $user,
        [],
        $user->role,
        resetToRoleDefaults: true,
    );

    expect(Feature::for($user)->active(StudentSchedule::class))->toBeTrue();
});
