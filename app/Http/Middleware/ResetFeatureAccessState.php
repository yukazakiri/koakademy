<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\FeatureAccessService;
use Closure;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps feature policy reads correct across long-lived workers.
 *
 * Octane keeps the booted application alive between requests, so a cached
 * policy snapshot would let one worker's write stay invisible to the others.
 * Reading fresh per request is the honest fix: a commit is visible to the next
 * request on every worker without depending on cache invalidation.
 */
final class ResetFeatureAccessState
{
    public function handle(Request $request, Closure $next): Response
    {
        Feature::flushCache();
        app(FeatureAccessService::class)->reset();

        // Popped here rather than in a share closure so it is consumed exactly
        // once, by a request that can actually render it.
        $notice = $request->hasSession() ? $request->session()->get('feature_notice') : null;
        if (is_array($notice) && $request->isMethod('GET') && ! $request->expectsJson()
            && $request->route()?->getName() === ($notice['destination'] ?? null)
            && (! $request->header('X-Inertia-Partial-Data') || in_array('featureNotice', explode(',', $request->header('X-Inertia-Partial-Data')), true))) {
            $request->session()->forget('feature_notice');
            $request->attributes->set('feature_notice', $notice);
        }

        return $next($request);
    }
}
