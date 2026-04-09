<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\OnboardingFeature;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

function portalHost(): string
{
    return env('PORTAL_HOST', 'portal.koakademy.test');
}

function portalUrl(string $path): string
{
    $normalized = str_starts_with($path, '/') ? $path : "/{$path}";

    return 'http://'.portalHost().$normalized;
}

it('redirects guests to login', function (): void {
    $this->get(portalUrl('/faculty/action-center'))
        ->assertRedirect('/login');
});

it('renders Action Center for verified faculty', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Instructor,
        'faculty_id_number' => 'FAC-001',
    ]);

    OnboardingFeature::factory()->create([
        'feature_key' => 'onboarding-faculty-action-center',
        'audience' => 'faculty',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(portalUrl('/faculty/action-center'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('faculty/action-center', false)
            ->has('action_center')
            ->has('action_center.activities')
        );
});
