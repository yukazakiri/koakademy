<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Models\HelpTicket;
use App\Models\OnboardingDismissal;
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
        'faculty-toolkit' => ['faculty-toolkit'],
        'faculty-at-risk-alerts' => ['faculty-toolkit-at-risk'],
        'faculty-assessments' => ['faculty-toolkit-assessments'],
        'faculty-inbox' => ['faculty-toolkit-inbox'],
        'faculty-office-hours' => ['faculty-toolkit-office-hours'],
        'faculty-requests-approvals' => ['faculty-toolkit-requests'],
        'faculty-insights' => ['faculty-toolkit-insights'],
        'faculty-action-center' => ['action-center'],
        'faculty-grades' => ['grades'],
        'faculty-attendance' => ['attendance'],
        'faculty-resources' => ['resources'],
        'faculty-forms' => ['forms'],
        'student-tuition' => ['tuition'],
        'student-schedule' => ['schedule'],
        'student-classes' => ['classes'],
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

        $dismissed = OnboardingDismissal::query()
            ->where('user_id', $user->id)
            ->pluck('feature_key')
            ->all();

        $allToggles = FeatureToggleRegistry::all();

        $featureClasses = collect($allToggles)
            ->map(fn ($toggle) => get_class($toggle))
            ->values()
            ->all();

        $featureValues = Feature::for($user)->values($featureClasses);

        return collect($allToggles)
            ->filter(function ($toggle) use ($audience, $featureValues): bool {
                if ($toggle->audience() !== 'all' && $toggle->audience() !== $audience) {
                    return false;
                }

                $featureClass = get_class($toggle);

                return (bool) ($featureValues[$featureClass] ?? false);
            })
            ->reject(fn ($toggle): bool => in_array($toggle->key(), $dismissed, true))
            ->values()
            ->map(fn ($toggle): array => [
                'featureKey' => $toggle->key(),
                'name' => $toggle->name(),
                'audience' => $toggle->audience(),
                'summary' => $toggle->summary(),
                'badge' => $toggle->badge(),
                'accent' => $toggle->accent(),
                'ctaLabel' => $toggle->ctaLabel(),
                'ctaUrl' => $toggle->ctaUrl(),
                'steps' => $toggle->steps(),
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
            $featureClass = FeatureToggleRegistry::classForKey($featureKey);
            $isActive = (bool) ($featureValues[$featureClass ?? $featureKey] ?? false);

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

        $featureClasses = collect(array_keys(self::FEATURE_TO_ROUTES))
            ->map(fn (string $key): ?string => FeatureToggleRegistry::classForKey($key))
            ->filter()
            ->values()
            ->all();

        return Feature::for($user)->values($featureClasses);
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
