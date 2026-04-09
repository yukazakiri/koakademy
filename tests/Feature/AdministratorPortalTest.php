<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

it('redirects guests away from administrator pages', function (): void {
    $this->get(portalUrlForAdministrators('/administrators/dashboard'))
        ->assertRedirect('/login');
});

it('forbids non-administrative users from administrator pages', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Instructor,
        'faculty_id_number' => 'FAC-101',
    ]);

    $this->actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/dashboard'))
        ->assertForbidden();
});

it('allows administrative users to view the administrator dashboard', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $this->actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/dashboard', false)
            ->has('admin_data')
            ->has('admin_data.stats')
            ->has('admin_data.quick_actions')
            ->has('settings')
        );
});

it('redirects authenticated administrators to administrators home from portal root', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $this->actingAs($user)
        ->get(portalUrlForAdministrators('/'))
        ->assertRedirect('/administrators');
});

it('redirects authenticated faculty to dashboard from portal root', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Instructor,
        'faculty_id_number' => 'FAC-202',
    ]);

    $this->actingAs($user)
        ->get(portalUrlForAdministrators('/'))
        ->assertRedirect(route('faculty.dashboard'));
});

it('redirects authenticated students from dashboard to student dashboard', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Student,
    ]);

    $this->actingAs($user)
        ->get(portalUrlForAdministrators('/dashboard'))
        ->assertRedirect(route('student.dashboard'));
});

it('redirects authenticated faculty from dashboard to faculty dashboard', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Instructor,
        'faculty_id_number' => 'FAC-303',
    ]);

    $this->actingAs($user)
        ->get(portalUrlForAdministrators('/dashboard'))
        ->assertRedirect(route('faculty.dashboard'));
});

it('redirects authenticated administrators from dashboard to administrators dashboard', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $this->actingAs($user)
        ->get(portalUrlForAdministrators('/dashboard'))
        ->assertRedirect(route('administrators.dashboard'));
});

it('redirects administrative users to administrators portal after login', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
        'password' => Illuminate\Support\Facades\Hash::make('password'),
    ]);

    $this->post(portalUrlForAdministrators('/login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/administrators');
});
