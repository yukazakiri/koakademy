<?php

declare(strict_types=1);

use App\Enums\UserRole;
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

    Feature::for($user)->activate('onboarding-faculty-toolkit');
    Feature::for($user)->deactivate([
        'onboarding-student-classes',
        'onboarding-student-schedule',
        'onboarding-student-tuition',
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

    expect(Feature::for($user)->active('onboarding-student-classes'))->toBeTrue()
        ->and(Feature::for($user)->active('onboarding-student-schedule'))->toBeTrue()
        ->and(Feature::for($user)->active('onboarding-student-tuition'))->toBeTrue()
        ->and(Feature::for($user)->active('onboarding-faculty-toolkit'))->toBeFalse();
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

    expect(Feature::for($user)->active('onboarding-student-schedule'))->toBeTrue();
});
