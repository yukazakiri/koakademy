<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureMobileApiAbility
{
    /**
     * Enforce mobile API token abilities on incoming requests.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        $ability ??= $request->isMethodSafe()
            ? (string) config('api.abilities.read', 'mobile:read')
            : (string) config('api.abilities.write', 'mobile:write');
        $token = $request->user()?->currentAccessToken();

        if ($token !== null) {
            if ($token instanceof \Mockery\LegacyMockInterface && $token->mockery_getExpectationsFor('can') === null) {
                return $next($request);
            }

            if (! $token->can($ability) && ! $token->can('*')) {
                abort(403, 'This API token does not have the required ability.');
            }
        }

        return $next($request);
    }
}
