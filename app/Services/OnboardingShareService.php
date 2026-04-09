<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Models\HelpTicket;
use App\Models\OnboardingDismissal;
use App\Models\OnboardingFeature;
use App\Models\User;
use Laravel\Pennant\Feature;

final class OnboardingShareService
{
    /**
     * Mapping of feature flag keys to sidebar route IDs.
     * When a feature is active, the corresponding sidebar routes become enabled.
     *
     * @var array<string, array<string>>
     */
    public const array FEATURE_TO_ROUTES = [
        'onboarding-faculty-toolkit' => ['faculty-toolkit'],
        'onboarding-faculty-at-risk-alerts' => ['faculty-toolkit-at-risk'],
        'onboarding-faculty-assessments' => ['faculty-toolkit-assessments'],
        'onboarding-faculty-inbox' => ['faculty-toolkit-inbox'],
        'onboarding-faculty-office-hours' => ['faculty-toolkit-office-hours'],
        'onboarding-faculty-requests-approvals' => ['faculty-toolkit-requests'],
        'onboarding-faculty-insights' => ['faculty-toolkit-insights'],
        'onboarding-faculty-action-center' => ['action-center'],
        'onboarding-faculty-grades' => ['grades'],
        'onboarding-faculty-attendance' => ['attendance'],
        'onboarding-faculty-resources' => ['resources'],
        'onboarding-faculty-forms' => ['forms'],
        'onboarding-student-tuition' => ['tuition'],
        'onboarding-student-schedule' => ['schedule'],
        'onboarding-student-classes' => ['classes'],
    ];

    /**
     * Get onboarding features for the user.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOnboardingFeatures(?User $user): array
    {
        if (! $user instanceof User) {
            return [];
        }

        $audience = $user->isStudentRole() ? 'student' : ($user->isFaculty() ? 'faculty' : 'all');

        $features = OnboardingFeature::query()
            ->where('is_active', true)
            ->whereIn('audience', [$audience, 'all'])
            ->get();

        $dismissed = OnboardingDismissal::query()
            ->where('user_id', $user->id)
            ->pluck('feature_key')
            ->all();

        $allFeatureKeys = array_unique(array_merge(
            $features->pluck('feature_key')->all(),
            array_keys(self::FEATURE_TO_ROUTES)
        ));

        $featureValues = Feature::for($user)->values($allFeatureKeys);

        return $features
            ->filter(fn (OnboardingFeature $feature): bool => (bool) ($featureValues[$feature->feature_key] ?? false))
            ->reject(fn (OnboardingFeature $feature): bool => in_array($feature->feature_key, $dismissed, true))
            ->values()
            ->map(fn (OnboardingFeature $feature): array => [
                'featureKey' => $feature->feature_key,
                'name' => $feature->name,
                'audience' => $feature->audience,
                'summary' => $feature->summary,
                'badge' => $feature->badge,
                'accent' => $feature->accent,
                'ctaLabel' => $feature->cta_label,
                'ctaUrl' => $feature->cta_url,
                'steps' => $feature->steps,
            ])
            ->all();
    }

    /**
     * Get sidebar feature flags from batched feature values.
     *
     * @param  array<string, mixed>  $featureValues
     * @return array<string, bool>
     */
    public function getSidebarFeatureFlags(array $featureValues): array
    {
        $enabledRoutes = [];

        foreach (self::FEATURE_TO_ROUTES as $featureKey => $routeIds) {
            $isActive = (bool) ($featureValues[$featureKey] ?? false);

            foreach ($routeIds as $routeId) {
                $enabledRoutes[$routeId] ??= false;
                if ($isActive) {
                    $enabledRoutes[$routeId] = true;
                }
            }
        }

        return $enabledRoutes;
    }

    /**
     * Get all feature values for a user (batched).
     *
     * @return array<string, mixed>
     */
    public function getAllFeatureValues(?User $user): array
    {
        if (! $user instanceof User) {
            return [];
        }

        $allFeatureKeys = array_keys(self::FEATURE_TO_ROUTES);

        return Feature::for($user)->values($allFeatureKeys);
    }

    /**
     * Get unresolved help tickets count for admins.
     */
    public function getUnresolvedHelpTicketsCount(?User $user): int
    {
        if (! $user instanceof User) {
            return 0;
        }

        if (! $user->hasRole(UserRole::Admin) && ! $user->hasRole(UserRole::SuperAdmin) && ! $user->hasRole(UserRole::Developer)) {
            return 0;
        }

        return HelpTicket::where('status', 'open')->count();
    }
}
