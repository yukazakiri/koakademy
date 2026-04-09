<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

it('allows super admins to access system management sections without explicit section permissions', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::SuperAdmin,
    ]);

    actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/system-management/seo'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/system-management/seo', false)
            ->where('access.sections.seo.can_view', true)
            ->where('access.sections.seo.can_update', true)
            ->where('access.sections.pulse.can_view', true)
            ->where('access.sections.pulse.can_update', false));
});
