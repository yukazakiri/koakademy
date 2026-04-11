<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Features\Onboarding\FeatureClassRegistry;
use Closure;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpFoundation\Response;

final class EnsureFeatureEnabled
{
    /**
     * Map of route patterns to feature keys.
     * Use * as a wildcard at the end of the pattern.
     */
    private const array ROUTE_MAP = [
        // Faculty Action Center
        'faculty.action-center*' => 'onboarding-faculty-action-center',

        // Faculty Academic Tools
        'faculty.classes.grades*' => 'onboarding-faculty-grades',
        'faculty.grades*' => 'onboarding-faculty-grades',
        'faculty.attendance*' => 'onboarding-faculty-attendance',
        'faculty.resources*' => 'onboarding-faculty-resources',
        'faculty.forms*' => 'onboarding-faculty-forms',

        // Faculty Toolkit
        'faculty.at-risk-alerts*' => 'onboarding-faculty-at-risk-alerts',
        'faculty.assessments*' => 'onboarding-faculty-assessments',
        'faculty.inbox*' => 'onboarding-faculty-inbox',
        'faculty.office-hours*' => 'onboarding-faculty-office-hours',
        'faculty.requests*' => 'onboarding-faculty-requests-approvals',
        'faculty.insights*' => 'onboarding-faculty-insights',

        // Student Features
        'student.classes*' => 'onboarding-student-classes',
        'student.tuition*' => 'onboarding-student-tuition',
        'student.schedule*' => 'onboarding-student-schedule',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        if (! $routeName) {
            return $next($request);
        }

        foreach (self::ROUTE_MAP as $pattern => $featureKey) {
            if (! $this->routeMatches($routeName, $pattern)) {
                continue;
            }
            // If the feature is inactive for the current user, abort
            $featureRef = FeatureClassRegistry::classForKey($featureKey) ?? $featureKey;
            if (! Feature::inactive($featureRef)) {
                continue;
            }
            // You might want to redirect or show a specific error page
            // abort(403, 'This feature is currently disabled.');
            // For a better UX, maybe redirect to dashboard with a flash message
            // But standard for unauthorized/forbidden is 403
            if ($request->expectsJson()) {
                abort(403, 'Feature disabled');
            }
            // If inertia request, maybe redirect back?
            // But 403 is safer to prevent access.
            abort(403, 'This feature is currently not available for your account.');
        }

        return $next($request);
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
