<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureAdministrativePortalAccess;
use App\Http\Middleware\FacultyIdValidationMiddleware;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetTenantContext;
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

        // Trust Traefik proxy & forwarded headers so Laravel detects HTTPS behind proxy
        $middleware->trustProxies(at: '*');
        $middleware->trustHosts([
            'localhost',
            '127.0.0.1',
            'portal\.dccp\.test',
            'admin\.dccp\.test',
            'admin\.dccp\.edu\.ph',
            'portal\.dccp\.edu\.ph',
            'portal\.dccp\.com',
            'admin\.dccp\.com',
        ]);

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
                return response()->json([
                    'error' => true,
                    'message' => $e->getMessage(),
                    'code' => $e instanceof Illuminate\Http\Exceptions\PostTooLargeException ? 'POST_TOO_LARGE' : 'SERVER_ERROR',
                ], $e instanceof Illuminate\Http\Exceptions\PostTooLargeException ? 413 : 500);
            }
        });
        Integration::handles($exceptions);
    })->create();
