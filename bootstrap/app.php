<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureAdministrativePortalAccess;
use App\Http\Middleware\FacultyIdValidationMiddleware;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetTenantContext;
use App\Support\HostingSecurity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',

    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance']);

        $middleware->trustProxies(at: HostingSecurity::trustedProxies());
        $middleware->trustHosts(at: HostingSecurity::trustedHostPatterns());

        $middleware->web(append: [
            App\Http\Middleware\EnsureSystemIsSetup::class,
            HandleInertiaRequests::class,
            App\Http\Middleware\OnlineUserTrackingMiddleware::class,
            SetTenantContext::class,
        ]);

        $middleware->alias([
            'faculty.verified' => FacultyIdValidationMiddleware::class,
            'administrators.only' => EnsureAdministrativePortalAccess::class,
            'faculty.only' => App\Http\Middleware\EnsureFacultyAccess::class,
            'student.only' => App\Http\Middleware\EnsureStudentAccess::class,
            'ensure.feature' => App\Http\Middleware\EnsureFeatureEnabled::class,
            'tenant.context' => SetTenantContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Illuminate\Http\Exceptions\PostTooLargeException $e, $request) {
            if ($request->expectsJson() || $request->is('classes/*/posts')) {
                return response()->json([
                    'error' => true,
                    'message' => 'The uploaded file(s) are too large. Maximum size is 50MB per file, 200MB total.',
                    'code' => 'POST_TOO_LARGE',
                ], 413);
            }
        });

        $exceptions->render(function (Throwable $e, $request) {
            if ($request->is('classes/*/posts') || $request->is('api/*')) {
                $statusCode = 500;
                $code = 'SERVER_ERROR';

                if ($e instanceof Illuminate\Auth\AuthenticationException) {
                    $statusCode = 401;
                    $code = 'UNAUTHENTICATED';
                } elseif ($e instanceof Illuminate\Validation\ValidationException) {
                    $statusCode = 422;
                    $code = 'VALIDATION_ERROR';

                    return response()->json([
                        'error' => true,
                        'message' => $e->getMessage(),
                        'errors' => $e->errors(),
                        'code' => $code,
                    ], $statusCode);
                } elseif ($e instanceof Illuminate\Http\Exceptions\ThrottleRequestsException) {
                    $statusCode = 429;
                    $code = 'RATE_LIMITED';
                } elseif ($e instanceof Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                    $statusCode = $e->getStatusCode();
                    $code = $statusCode === 403 ? 'FORBIDDEN' : ($statusCode === 404 ? 'NOT_FOUND' : 'HTTP_ERROR');
                } elseif ($e instanceof Spatie\Permission\Exceptions\UnauthorizedException) {
                    $statusCode = 403;
                    $code = 'FORBIDDEN';
                } elseif ($e instanceof Illuminate\Http\Exceptions\PostTooLargeException) {
                    $statusCode = 413;
                    $code = 'POST_TOO_LARGE';
                }

                return response()->json([
                    'error' => true,
                    'message' => $e->getMessage(),
                    'code' => $code,
                ], $statusCode);
            }
        });
        Integration::handles($exceptions);
    })->create();
