<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

final class OnlineUserTrackingMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $userId = $request->user()?->id;

        if ($userId !== null && config('session.driver') === 'redis') {
            $connection = config('session.connection') ?? 'default';
            $key = config('cache.prefix', '').'online-users';

            Redis::connection($connection)->zadd($key, [
                (string) $userId => now()->timestamp,
            ]);

            Redis::connection($connection)->zremrangebyscore(
                $key,
                0,
                now()->subMinutes(15)->timestamp - 1
            );
        }

        return $response;
    }
}
