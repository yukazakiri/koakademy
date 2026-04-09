<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('shows setup page when no super admin exists', function () {
    $response = get('/setup');

    $response->assertStatus(200);
});

it('redirects to login when super admin already exists on GET', function () {
    $school = School::factory()->create();
    User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'school_id' => $school->id,
    ]);

    $response = get('/setup');

    $response->assertRedirectContains('/login');
});

it('redirects to login when super admin already exists on POST', function () {
    $school = School::factory()->create();
    User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'school_id' => $school->id,
    ]);

    $response = post('/setup', [
        'admin_name' => 'John Doe',
        'admin_email' => 'admin@example.com',
        'admin_password' => 'Password123!',
        'admin_password_confirmation' => 'Password123!',
        'school_name' => 'Awesome School',
        'school_code' => 'AS',
        'school_starting_date' => '2025-08-01',
        'school_ending_date' => '2026-05-31',
        'semester' => '1',
    ]);

    $response->assertRedirectContains('/login');
});

it('creates super admin and school on valid setup', function () {
    $roleName = config('filament-shield.super_admin.name', 'super_admin');
    Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

    $response = post('/setup', [
        'admin_name' => 'Super Admin',
        'admin_email' => 'super@example.com',
        'admin_password' => 'Password123!',
        'admin_password_confirmation' => 'Password123!',
        'school_name' => 'Test School',
        'school_code' => 'TST',
        'school_email' => 'contact@testschool.com',
        'school_starting_date' => '2025-08-01',
        'school_ending_date' => '2026-05-31',
        'semester' => '1',
    ]);

    $response->assertRedirectContains('/');

    assertDatabaseHas(School::class, [
        'name' => 'Test School',
        'code' => 'TST',
        'email' => 'contact@testschool.com',
    ]);

    assertDatabaseHas(User::class, [
        'name' => 'Super Admin',
        'email' => 'super@example.com',
        'role' => UserRole::SuperAdmin->value,
    ]);

    assertAuthenticated();

    $user = User::where('email', 'super@example.com')->first();
    expect($user->hasRole($roleName))->toBeTrue();
});

it('fails validation when required fields are missing', function () {
    $response = post('/setup', []);

    $response->assertSessionHasErrors([
        'admin_name',
        'admin_email',
        'admin_password',
        'school_name',
        'school_code',
        'school_starting_date',
        'school_ending_date',
        'semester',
    ]);
});

it('fails validation when semester is not a valid value', function () {
    $response = post('/setup', [
        'admin_name' => 'Super Admin',
        'admin_email' => 'super@example.com',
        'admin_password' => 'Password123!',
        'admin_password_confirmation' => 'Password123!',
        'school_name' => 'Test School',
        'school_code' => 'TST',
        'school_starting_date' => '2025-08-01',
        'school_ending_date' => '2026-05-31',
        'semester' => '9',
    ]);

    $response->assertSessionHasErrors(['semester']);
});

it('fails validation when school year end is before start', function () {
    $response = post('/setup', [
        'admin_name' => 'Super Admin',
        'admin_email' => 'super@example.com',
        'admin_password' => 'Password123!',
        'admin_password_confirmation' => 'Password123!',
        'school_name' => 'Test School',
        'school_code' => 'TST',
        'school_starting_date' => '2026-05-31',
        'school_ending_date' => '2025-08-01',
        'semester' => '1',
    ]);

    $response->assertSessionHasErrors(['school_ending_date']);
});
