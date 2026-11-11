<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\TenantContext;
use Spatie\Permission\Models\Permission;

/**
 * SEC-005 · Bearer-token API requests are not tenant-scoped.
 *
 * Verifies that:
 * 1. Authenticated API requests without a resolvable tenant are rejected.
 * 2. Tenant middleware resolves a school after bearer authentication.
 * 3. Cross-school identifiers and headers cannot widen the tenant scope.
 * 4. The SchoolScope fails closed when a request requires a tenant.
 * 5. Platform administrators keep their deliberate cross-school access.
 */
beforeEach(function (): void {
    config(['activitylog.enabled' => false]);

    Permission::findOrCreate('ViewAny:Student', 'web');
    Permission::findOrCreate('ViewAny:ClassEnrollment', 'web');

    $this->schoolA = School::factory()->create();
    $this->schoolB = School::factory()->create();

    $this->studentA = Student::factory()->create(['school_id' => $this->schoolA->id]);
    $this->studentB = Student::factory()->create(['school_id' => $this->schoolB->id]);

    $this->classA = Classes::factory()->create(['school_id' => $this->schoolA->id]);
    $this->classB = Classes::factory()->create(['school_id' => $this->schoolB->id]);

    $this->enrollmentA = ClassEnrollment::factory()->create([
        'school_id' => $this->schoolA->id,
        'class_id' => $this->classA->id,
        'student_id' => $this->studentA->id,
    ]);
    $this->enrollmentB = ClassEnrollment::factory()->create([
        'school_id' => $this->schoolB->id,
        'class_id' => $this->classB->id,
        'student_id' => $this->studentB->id,
    ]);

    $this->registrarA = User::factory()->create([
        'role' => UserRole::Registrar,
        'school_id' => $this->schoolA->id,
    ]);
    $this->registrarA->givePermissionTo(['ViewAny:ClassEnrollment', 'ViewAny:Student']);

    $this->tenantlessUser = User::factory()->create([
        'role' => UserRole::Registrar,
        'school_id' => null,
    ]);
    $this->tenantlessUser->givePermissionTo(['ViewAny:ClassEnrollment', 'ViewAny:Student']);

    $this->platformAdmin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => null,
    ]);
});

it('rejects an authenticated API request when no tenant can be resolved', function (): void {
    $this->actingAs($this->tenantlessUser, 'sanctum')
        ->getJson('/api/class-enrollments')
        ->assertForbidden();

    // Filament API service routes (which are outside the api middleware group)
    // are protected by the same tenant middleware.
    $this->actingAs($this->tenantlessUser, 'sanctum')
        ->getJson('/api/students')
        ->assertForbidden();
});

it('scopes class enrollment listings to the resolved school', function (): void {
    $response = $this->actingAs($this->registrarA, 'sanctum')
        ->getJson('/api/class-enrollments');

    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($this->enrollmentA->id)
        ->and($ids)->not->toContain($this->enrollmentB->id);
});

it('scopes Filament API service listings to the resolved school', function (): void {
    $response = $this->actingAs($this->registrarA, 'sanctum')
        ->getJson('/api/students');

    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($this->studentA->id)
        ->and($ids)->not->toContain($this->studentB->id);
});

it('rejects a school header for a school the caller cannot access', function (): void {
    $this->actingAs($this->registrarA, 'sanctum')
        ->withHeader('X-KoAkademy-School', (string) $this->schoolB->id)
        ->getJson('/api/class-enrollments')
        ->assertForbidden();
});

it('honours a school header for another accessible organization', function (): void {
    $this->registrarA->addToOrganization($this->schoolB);

    $response = $this->actingAs($this->registrarA, 'sanctum')
        ->withHeader('X-KoAkademy-School', (string) $this->schoolB->id)
        ->getJson('/api/class-enrollments');

    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($this->enrollmentB->id)
        ->and($ids)->not->toContain($this->enrollmentA->id);
});

it('fails closed when a request requires a tenant but none is resolved', function (): void {
    $tenantContext = app(TenantContext::class);
    $tenantContext->requireTenant();

    expect(Student::query()->count())->toBe(0)
        ->and(Student::withoutSchoolScope()->count())->toBe(2);
});

it('keeps deliberate cross-school access for platform administrators', function (): void {
    $response = $this->actingAs($this->platformAdmin, 'sanctum')
        ->getJson('/api/class-enrollments');

    $response->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($this->enrollmentA->id)
        ->and($ids)->toContain($this->enrollmentB->id);
});
