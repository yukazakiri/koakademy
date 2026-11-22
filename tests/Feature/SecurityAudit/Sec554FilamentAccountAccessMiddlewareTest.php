<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function (): void {
    GeneralSetting::factory()->create([
        'school_starting_date' => '2026-06-01',
        'school_ending_date' => '2027-03-31',
        'semester' => 1,
    ]);
});

it('denies Filament panel access to suspended administrators via canAccessPanel and middleware', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'suspended_at' => now(),
    ]);

    expect($admin->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertForbidden();
});

it('revokes an active Filament admin session when session_version is incremented', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'session_version' => 2,
    ]);

    $this->actingAs($admin)
        ->withSession(['user_session_version' => 1])
        ->get('/admin')
        ->assertForbidden();

    $this->assertGuest();
});

it('allows an active non-suspended administrator with matching session_version to reach /admin', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'session_version' => 3,
    ]);

    expect($admin->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();

    $this->actingAs($admin)
        ->withSession(['user_session_version' => 3])
        ->get('/admin')
        ->assertSuccessful();
});
