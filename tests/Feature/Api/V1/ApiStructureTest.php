<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

/**
 * Guard tests for the API restructure: the canonical versioned paths and
 * the legacy unversioned aliases must stay registered and authenticated so
 * existing mobile and web clients keep working.
 */

it('keeps legacy and v1 student verification paths registered', function (): void {
    Sanctum::actingAs(User::factory()->create());

    // Deliberately invalid payloads: routed endpoints must reach validation
    // instead of 404ing, proving the paths resolve.
    $this->postJson('/api/students/verify', [])->assertUnprocessable();
    $this->postJson('/api/v1/students/verify', [])->assertUnprocessable();
});

it('registers the staff and faculty surfaces on both the versioned paths and their legacy aliases', function (): void {
    // Some of these controllers legitimately answer 404 for a user without
    // a linked record, so routing is asserted by comparing a request against
    // Laravel's route table instead of response status codes.
    $paths = [
        // [legacy, versioned, method]
        ['/api/settings', '/api/v1/settings', 'GET'],
        ['/api/enrollments', '/api/v1/enrollments', 'GET'],
        ['/api/class-enrollments', '/api/v1/class-enrollments', 'GET'],
        ['/api/class-posts', '/api/v1/class-posts', 'GET'],
        ['/api/profile/me', '/api/v1/profile/me', 'GET'],
        ['/api/faculty/profile', '/api/v1/faculty/profile', 'GET'],
        ['/api/faculty/classes', '/api/v1/faculty/classes', 'GET'],
        ['/api/jobs', '/api/v1/jobs', 'GET'],
        ['/api/organizations', '/api/v1/organizations', 'GET'],
        ['/api/v1/public/settings', null, 'GET'],
        ['/api/v1/auth/login', null, 'POST'],
        ['/api/v1/auth/signup', null, 'POST'],
    ];

    foreach ($paths as [$legacy, $versioned, $method]) {
        foreach (array_filter([$legacy, $versioned]) as $path) {
            expect(
                collect(Route::getRoutes())
                    ->contains(
                        fn ($route): bool => in_array($method, $route->methods()) && $route->uri() === ltrim((string) $path, '/'),
                    ),
                "Expected {$method} {$path} to be a registered route",
            )->toBeTrue();
        }
    }
});

it('points the legacy aliases at the same controllers as the v1 routes', function (): void {
    $pairs = [
        ['/api/settings', '/api/v1/settings'],
        ['/api/faculty/profile', '/api/v1/faculty/profile'],
        ['/api/enrollments', '/api/v1/enrollments'],
    ];

    $routes = collect(Route::getRoutes());

    foreach ($pairs as [$legacy, $versioned]) {
        $legacyAction = $routes->first(fn ($route): bool => $route->uri() === ltrim($legacy, '/') && in_array('GET', $route->methods()))?->getActionName();
        $versionedAction = $routes->first(fn ($route): bool => $route->uri() === ltrim($versioned, '/') && in_array('GET', $route->methods()))?->getActionName();

        expect($legacyAction)->not->toBeNull()
            ->and($versionedAction)->toBe($legacyAction);
    }
});

it('rejects unauthenticated api calls instead of redirecting', function (): void {
    foreach (['/api/user', '/api/v1/auth/me', '/api/v1/settings', '/api/v1/faculty/profile'] as $path) {
        $response = $this->getJson($path);

        expect($response->status())->toBe(401, "Expected {$path} to return 401 for guests");
    }
});

it('requires a session for the internal jobs endpoints', function (): void {
    // The Inertia SPA consumes /api/jobs with session auth; token-only
    // clients should not be distracted by session routes here.
    $this->getJson('/api/jobs')->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/jobs')->assertOk();
});

it('returns a consistent error envelope for unauthenticated api callers', function (): void {
    $this->getJson('/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertJson([
            'error' => true,
            'code' => 'UNAUTHENTICATED',
        ]);
});
