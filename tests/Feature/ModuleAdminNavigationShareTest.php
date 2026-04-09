<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Http\Request;

use function Pest\Laravel\actingAs;

it('shares enabled module admin routes for inertia consumers', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    actingAs($user);

    $request = Request::create('/administrators/dashboard');
    $request->setUserResolver(static fn (): User => $user);

    $shared = app(HandleInertiaRequests::class)->share($request);

    $hasAnnouncementRoute = collect($shared['moduleAdminRoutes'])
        ->contains(fn (array $route): bool => $route['id'] === 'admin-announcements'
            && $route['link'] === '/administrators/announcements'
            && $route['module'] === 'Announcement');

    expect($shared['moduleAdminRoutes'])->toBeArray()
        ->and($hasAnnouncementRoute)->toBeTrue();
});
