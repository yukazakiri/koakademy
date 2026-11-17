<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateMcp extends Authenticate
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle($request, Closure $next, ...$guards): Response
    {
        $token = $request->bearerToken();

        // Passport access tokens are JWTs; Sanctum keys remain accepted as before.
        // Avoid resolving Passport's signing keys for anonymous or Sanctum requests.
        $guard = $token !== null && ! str_contains($token, '|') && mb_substr_count($token, '.') === 2 ? 'mcp-oauth' : 'sanctum';

        return parent::handle($request, $next, $guard);
    }
}
