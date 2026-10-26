<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Http\Controllers\Api\V1\ApiPagination;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    config([
        'activitylog.enabled' => false,
        'api.default_per_page' => 20,
        'api.max_per_page' => 100,
    ]);

    GeneralSetting::create([
        'school_starting_date' => now()->startOfYear(),
        'school_ending_date' => now()->startOfYear()->addYear(),
        'semester' => 1,
        'site_name' => 'Test School',
    ]);

    Permission::firstOrCreate(['name' => 'ViewAny:Student', 'guard_name' => 'web']);
});

it('clamps requested page sizes to the configured bounds', function (): void {
    expect(ApiPagination::perPage())
        ->toBe(20)
        ->and(ApiPagination::perPage(1000000))
        ->toBe(100)
        ->and(ApiPagination::perPage('1000000'))
        ->toBe(100)
        ->and(ApiPagination::perPage('all'))
        ->toBe(100)
        ->and(ApiPagination::perPage(0))
        ->toBe(1)
        ->and(ApiPagination::perPage(-10))
        ->toBe(1)
        ->and(ApiPagination::perPage('not-a-number'))
        ->toBe(20)
        ->and(ApiPagination::perPage(15))
        ->toBe(15);

    config(['api.default_per_page' => 10, 'api.max_per_page' => 25]);

    expect(ApiPagination::perPage())
        ->toBe(10)
        ->and(ApiPagination::perPage(500))
        ->toBe(25);
});

it('caps the page size the students api returns', function (): void {
    config(['api.max_per_page' => 5]);

    $user = User::factory()->create([
        'role' => UserRole::Student,
        'email' => 'student@example.com',
    ]);

    Student::factory()->create([
        'user_id' => $user->id,
        'email' => $user->email,
        'document_location_id' => null,
    ]);

    Student::factory()->count(7)->create(['document_location_id' => null]);

    $user->givePermissionTo('ViewAny:Student');
    Sanctum::actingAs($user);

    $this->getJson('/api/students?per_page=1000000')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 5)
        ->assertJsonCount(5, 'data');
});

it('does not let the class admin page size exceed the configured ceiling', function (): void {
    config(['api.max_per_page' => 15]);

    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(route('administrators.classes.index', ['per_page' => 1000000]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filters.per_page', 15));
});

it('does not let the enrollment admin page size exceed the configured ceiling', function (): void {
    config(['api.max_per_page' => 15]);

    $enrollment = StudentEnrollment::factory()->create(['school_year' => '2024 - 2025', 'semester' => 1]);
    $enrollment->forceFill(['workflow_runtime' => StudentEnrollment::WorkflowRuntimePolicyV1])->save();

    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(route('administrators.enrollments.index', ['per_page' => 1000000]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('workflow_setup_required', false)
            ->where('filters.per_page', 15));
});

it('does not let the applicants admin page size exceed the configured ceiling', function (): void {
    config(['api.max_per_page' => 15]);

    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(route('administrators.enrollments.applicants', ['per_page' => 1000000]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('applicants.per_page', 15));
});
