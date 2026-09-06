<?php

declare(strict_types=1);

use App\Enums\SchoolLevel;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\User;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
});

it('shows the setup screen when the database is empty', function (): void {
    $this->get('/setup')->assertOk();

    expect(GeneralSetting::query()->exists())->toBeFalse();
});

it('marks the application as setup when data exists and blocks access', function (): void {
    User::factory()->create();

    $this->get('/setup')->assertForbidden();

    $settings = GeneralSetting::query()->first();
    expect($settings)->not->toBeNull();
    expect($settings?->is_setup)->toBeTrue();
});

it('blocks access to setup after setup is complete', function (): void {
    GeneralSetting::factory()->create(['is_setup' => true]);
    $user = User::factory()->create();

    $this->actingAs($user)->get('/setup')->assertForbidden();
});

it('stores the selected school level during setup', function (): void {
    $this->post('/setup', validSetupPayload([
        'school_level' => SchoolLevel::SeniorHigh->value,
    ]))->assertRedirect('/');

    $school = School::query()->first();

    expect($school)->not->toBeNull();
    expect($school?->school_level)->toBe(SchoolLevel::SeniorHigh);
    expect(GeneralSetting::query()->first()?->is_setup)->toBeTrue();
});

it('stores the normalized country code during setup', function (): void {
    $this->post('/setup', validSetupPayload([
        'country_code' => ' us ',
    ]))->assertRedirect('/');

    $school = School::query()->first();

    expect($school)->not->toBeNull();
    expect($school?->country_code)->toBe('US');
    expect(GeneralSetting::query()->first()?->is_setup)->toBeTrue();
});

it('requires a country code during setup', function (): void {
    $payload = validSetupPayload();
    unset($payload['country_code']);

    $this->post('/setup', $payload)
        ->assertSessionHasErrors('country_code');
});

it('rejects an invalid country code during setup', function (): void {
    $this->post('/setup', validSetupPayload([
        'country_code' => 'ZZ',
    ]))->assertSessionHasErrors('country_code');
});

it('requires a school level during setup', function (): void {
    $payload = validSetupPayload();
    unset($payload['school_level']);

    $this->post('/setup', $payload)
        ->assertSessionHasErrors('school_level');
});

it('rejects an invalid school level during setup', function (): void {
    $this->post('/setup', validSetupPayload([
        'school_level' => 'preschool',
    ]))->assertSessionHasErrors('school_level');
});

function validSetupPayload(array $overrides = []): array
{
    return array_merge([
        'admin_name' => 'System Administrator',
        'admin_email' => 'admin@example.edu',
        'admin_password' => 'password123',
        'admin_password_confirmation' => 'password123',
        'school_name' => 'Example Academy',
        'school_code' => 'EXA',
        'country_code' => 'PH',
        'school_level' => SchoolLevel::HigherEducation->value,
        'school_description' => 'Example institution.',
        'school_email' => 'info@example.edu',
        'school_phone' => '+63 2 1234 5678',
        'school_location' => 'Main Campus',
        'dean_name' => 'Dr. Jane Smith',
        'dean_email' => 'dean@example.edu',
        'school_starting_date' => '2026-06-01',
        'school_ending_date' => '2027-03-31',
        'semester' => '1',
        'curriculum_year' => '2026-2027',
    ], $overrides);
}
