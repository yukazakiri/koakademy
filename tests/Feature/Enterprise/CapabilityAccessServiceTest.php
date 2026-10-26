<?php

declare(strict_types=1);

use App\Enterprise\CapabilityAccessService;
use App\Enterprise\CapabilityRegistry;
use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;
use App\Modules\ModuleManifestRepository;
use App\Modules\ModuleStateRepository;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EnterpriseLicense;

it('discovers paid capabilities from installed module manifests', function (): void {
    $capability = app(CapabilityRegistry::class)->find(EnterpriseLicense::CAPABILITY);

    expect($capability?->module)->toBe('EnterpriseReporting');
    expect($capability?->permission)->toBe('ViewAny:StudentEnrollment');
});

it('allows a licensed and enabled capability for an authorized school member', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id, 'role' => UserRole::Registrar]);
    $user->givePermissionTo(Permission::findOrCreate('ViewAny:StudentEnrollment', 'web'));
    EnterpriseLicense::activate($school);

    expect(app(CapabilityAccessService::class)->decide(EnterpriseLicense::CAPABILITY, $user, $school)->allowed)->toBeTrue();
});

it('does not let a super administrator or feature assignment bypass a missing purchase', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $user->assignRole(UserRole::SuperAdmin);
    DB::table('school_capabilities')->insert(['school_id' => $school->id, 'capability_key' => EnterpriseLicense::CAPABILITY, 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('feature_assignments')->insert(['feature_key' => EnterpriseLicense::CAPABILITY, 'subject_type' => 'user', 'subject_key' => (string) $user->id, 'effect' => 'allow', 'source' => 'administrator', 'created_at' => now(), 'updated_at' => now()]);

    $decision = app(CapabilityAccessService::class)->decide(EnterpriseLicense::CAPABILITY, $user, $school);

    expect($decision->allowed)->toBeFalse();
    expect($decision->reason)->toBe('School license does not include this capability');
});

it('denies access when a required boundary is missing', function (string $boundary): void {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id, 'role' => UserRole::Registrar]);
    if ($boundary !== 'permission') {
        $user->givePermissionTo(Permission::findOrCreate('ViewAny:StudentEnrollment', 'web'));
    }
    EnterpriseLicense::activate($school);

    if ($boundary === 'school') {
        $user->school_id = null;
    } elseif ($boundary === 'inactive') {
        $school->is_active = false;
    } elseif ($boundary === 'enabled') {
        DB::table('school_capabilities')->where('school_id', $school->id)->update(['enabled' => false]);
    } elseif ($boundary === 'package') {
        DB::table('module_installations')->insert(['module_name' => 'EnterpriseReporting', 'enabled' => false, 'created_at' => now(), 'updated_at' => now()]);
        app()->forgetInstance(ModuleStateRepository::class);
    }

    expect(app(CapabilityAccessService::class)->decide(EnterpriseLicense::CAPABILITY, $user, $school)->allowed)->toBeFalse();
})->with(['permission', 'school', 'inactive', 'enabled', 'package']);

it('fails closed when the optional package is absent', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);
    EnterpriseLicense::activate($school);
    app()->instance(ModuleManifestRepository::class, new ModuleManifestRepository(app(ModuleStateRepository::class), '/no-enterprise-packages'));

    expect(app(CapabilityAccessService::class)->decide(EnterpriseLicense::CAPABILITY, $user, $school)->reason)->toBe('Capability not installed');
});

it('does not reuse another schools license when the context changes', function (): void {
    $licensed = School::factory()->create();
    $unlicensed = School::factory()->create();
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $user->assignRole(UserRole::SuperAdmin);
    EnterpriseLicense::activate($licensed);
    $service = app(CapabilityAccessService::class);

    expect($service->decide(EnterpriseLicense::CAPABILITY, $user, $licensed)->allowed)->toBeTrue();
    expect($service->decide(EnterpriseLicense::CAPABILITY, $user, $unlicensed)->allowed)->toBeFalse();
    expect($service->decide(EnterpriseLicense::CAPABILITY, $user, $licensed)->allowed)->toBeTrue();
});
