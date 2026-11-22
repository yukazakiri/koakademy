<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

it('does not expose tenant schools to unauthenticated visitors on /login', function (): void {
    School::factory()->create(['name' => 'Secret Tenant A', 'code' => 'SECA', 'is_active' => true]);
    School::factory()->create(['name' => 'Secret Tenant B', 'code' => 'SECB', 'is_active' => true]);

    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('settings.availableSchools', []));
});

it('scopes availableSchools to the authenticated user accessible organizations', function (): void {
    $schoolA = School::factory()->create(['name' => 'Tenant A', 'code' => 'TENA', 'is_active' => true]);
    $schoolB = School::factory()->create(['name' => 'Tenant B', 'code' => 'TENB', 'is_active' => true]);

    $student = User::factory()->create([
        'role' => UserRole::Student,
        'school_id' => $schoolA->id,
    ]);
    $student->addToOrganization($schoolA, ['is_primary' => true, 'role' => 'student']);

    $this->actingAs($student);

    $settings = app(App\Services\SettingsShareService::class)->getSettings($student);
    $codes = array_column($settings['availableSchools'], 'code');

    expect($codes)->toContain('TENA')
        ->and($codes)->not->toContain('TENB');
});
