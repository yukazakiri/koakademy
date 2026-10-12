<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Features\FeatureDecision;
use App\Features\FeatureDenial;
use App\Models\User;
use App\Services\FeatureAccessService;
use App\Services\FeatureToggleRegistry;
use Closure;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates feature routes.
 *
 * A feature can be unavailable for two very different reasons. An administrator
 * deliberately withheld it, which is a 403. Or it simply is not part of the
 * user's current release, which is an ordinary part of gradual rollout and must
 * not look like a permission failure to a student following an old bookmark.
 */
final class EnsureFeatureEnabled
{
    /**
     * Map of route patterns to feature keys.
     * Use * as a wildcard at the end of the pattern.
     *
     * @var array<string, string>
     */
    private const array ROUTE_MAP = [
        // Faculty Action Center
        'faculty.action-center*' => 'faculty-action-center',

        // Faculty Academic Tools
        'faculty.classes.grades*' => 'faculty-grades',
        'faculty.grades*' => 'faculty-grades',
        'faculty.attendance*' => 'faculty-attendance',
        'faculty.resources*' => 'faculty-resources',
        'faculty.forms*' => 'faculty-forms',

        // Faculty Toolkit
        'faculty.at-risk-alerts*' => 'faculty-at-risk-alerts',
        'faculty.assessments*' => 'faculty-assessments',
        'faculty.inbox*' => 'faculty-inbox',
        'faculty.office-hours*' => 'faculty-office-hours',
        'faculty.requests*' => 'faculty-requests-approvals',
        'faculty.insights*' => 'faculty-insights',

        // Student Features
        'student.classes*' => 'student-classes',
        'student.tuition*' => 'student-tuition',
        'student.schedule*' => 'student-schedule',
    ];

    public function __construct(private readonly FeatureAccessService $access) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        if ($routeName === null) {
            return $next($request);
        }

        foreach (self::ROUTE_MAP as $pattern => $featureKey) {
            if (! $this->routeMatches($routeName, $pattern)) {
                continue;
            }

            $decision = $this->decision($featureKey, $request->user());

            if ($decision->active) {
                continue;
            }

            if ($decision->denial === FeatureDenial::NotReleased && $request->isMethod('GET') && ! $request->expectsJson()) {
                return $this->redirectToPortal($routeName, $featureKey, $decision->reason);
            }

            abort(403, $request->expectsJson() ? 'Feature disabled' : $this->forbiddenMessage($decision->reason));
        }

        return $next($request);
    }

    private function decision(string $featureKey, mixed $user): FeatureDecision
    {
        $key = FeatureToggleRegistry::canonicalKey($featureKey);
        $featureClass = FeatureToggleRegistry::classForKey($key);

        if ($featureClass === null) {
            return new FeatureDecision(false, 'Unknown feature', FeatureDenial::Excluded);
        }

        // Compatibility mode keeps the pre-revamp behaviour exactly.
        if ($this->access->definition($key)?->mode !== 'enforced') {
            $active = Feature::for($user)->inactive($featureClass)
                ? new FeatureDecision(false, 'Feature disabled', FeatureDenial::Excluded)
                : new FeatureDecision(true, 'Active');

            return $active;
        }

        return $this->access->decideForCurrentContext($key, $user instanceof User ? $user : null);
    }

    private function redirectToPortal(string $routeName, string $featureKey, string $reason): Response
    {
        $dashboard = match (true) {
            str_starts_with($routeName, 'student.') => 'student.dashboard',
            str_starts_with($routeName, 'faculty.') => 'faculty.dashboard',
            default => null,
        };

        $fallback = url('/');

        // Deliberately not flash data: a flash key is consumed by whichever
        // request happens to land next, and background polling on the same
        // session would swallow the notice before the dashboard rendered it.
        session()->put('feature_notice', [
            'destination' => $dashboard,
            'title' => 'Not available yet',
            'message' => $this->forbiddenMessage($reason, contextual: true).' An administrator can release it for you.',
            'feature' => $featureKey,
        ]);

        return redirect()->to($dashboard ? route($dashboard) : $fallback);
    }

    private function forbiddenMessage(string $reason, bool $contextual = false): string
    {
        return match ($reason) {
            'Role not eligible' => 'This feature is not available for your account.',
            'Disabled by administrator' => 'This feature is currently turned off.',
            'Explicit administrator exclusion' => 'This feature is currently not available for your account.',
            'Retired by administrator' => 'This feature has been retired.',
            'Scheduled for a later release' => 'This feature has not been released yet.',
            'Outside current rollout' => 'This feature is not part of your current release.',
            'No matching target' => 'This feature is not part of your current release.',
            'Not enabled by default' => 'This feature is not enabled by default.',
            'Beta opt-out' => 'You have opted out of this feature.',
            default => $contextual ? 'This feature is not available to you right now.' : 'This feature is currently not available for your account.',
        };
    }

    /**
     * Check if route name matches the pattern.
     */
    private function routeMatches(string $routeName, string $pattern): bool
    {
        if (str_ends_with($pattern, '*')) {
            $base = mb_substr($pattern, 0, -1);

            return str_starts_with($routeName, $base);
        }

        return $routeName === $pattern;
    }
}
