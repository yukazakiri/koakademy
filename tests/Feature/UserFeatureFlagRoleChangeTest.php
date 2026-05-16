<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Features\Toggles\FacultyToolkit;
use App\Features\Toggles\StudentClasses;
use App\Features\Toggles\StudentSchedule;
use App\Features\Toggles\StudentTuition;
use App\Models\User;
use App\Services\FeatureToggleRegistry;
use App\Services\UserFeatureFlagService;
use Laravel\Pennant\Feature;

beforeEach(function (): void {
    config([
        'onboarding.experimental_feature_keys' => [
            'faculty-toolkit',
            'student-classes',
            'student-schedule',
            'student-tuition',
        ],
        'onboarding.experimental_features_roles' => [
            'faculty-toolkit' => ['faculty'],
            'student-classes' => ['student', 'shs_student', 'graduate_student'],
            'student-schedule' => ['student', 'shs_student', 'graduate_student'],
            'student-tuition' => ['student', 'shs_student', 'graduate_student'],
        ],
    ]);

    Feature::purge(FacultyToolkit::class);
    Feature::purge(StudentClasses::class);
    Feature::purge(StudentSchedule::class);
    Feature::purge(StudentTuition::class);
});

it('returns only experimental features allowed for the selected role', function (): void {
    $options = app(UserFeatureFlagService::class)->featureOptionsForRole(UserRole::Student);

    expect(array_keys($options))
        ->toBe([
            'student-classes',
            'student-schedule',
            'student-tuition',
        ])
        ->not->toContain('faculty-toolkit');
});

it('resets stale feature overrides when a user role changes', function (): void {
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

it('FeatureToggleRegistry resolves toggle classes correctly', function (): void {
    expect(FeatureToggleRegistry::classForKey('faculty-toolkit'))->toBe(FacultyToolkit::class);
    expect(FeatureToggleRegistry::classForKey('student-classes'))->toBe(StudentClasses::class);
    expect(FeatureToggleRegistry::classForKey('student-schedule'))->toBe(StudentSchedule::class);
    expect(FeatureToggleRegistry::classForKey('student-tuition'))->toBe(StudentTuition::class);
});

it('FeatureToggleRegistry returns null for unknown keys', function (): void {
    expect(FeatureToggleRegistry::classForKey('nonexistent-feature'))->toBeNull();
});
