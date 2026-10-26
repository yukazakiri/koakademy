<?php

declare(strict_types=1);

use App\Enterprise\EntitlementService;
use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\Support\EnterpriseLicense;

it('imports an offline license through an administrator endpoint', function (): void {
    EnterpriseLicense::configure();
    $school = School::factory()->create();
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $user->assignRole(UserRole::SuperAdmin);
    $document = EnterpriseLicense::sign(EnterpriseLicense::claims($school));

    $this->actingAs($user)->post(route('administrators.enterprise.licenses.store', $school), [
        'license' => UploadedFile::fake()->createWithContent('school.license', $document),
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('school_entitlements', ['school_id' => $school->id, 'signed_license' => $document, 'imported_by' => $user->id]);
    expect(app(EntitlementService::class)->grants($school, EnterpriseLicense::CAPABILITY))->toBeTrue();
});

it('rejects an invalid license without replacing the active license', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $user->assignRole(UserRole::SuperAdmin);
    EnterpriseLicense::activate($school);
    $original = DB::table('school_entitlements')->where('school_id', $school->id)->value('signed_license');

    $this->actingAs($user)->post(route('administrators.enterprise.licenses.store', $school), [
        'license' => UploadedFile::fake()->createWithContent('invalid.license', '{'),
    ])->assertSessionHasErrors('license');

    $this->assertDatabaseHas('school_entitlements', ['school_id' => $school->id, 'signed_license' => $original]);
});

it('prevents non administrators from importing licenses or activating capabilities', function (UserRole $role): void {
    $school = School::factory()->create();
    $user = User::factory()->create(['role' => $role, 'school_id' => $school->id]);

    $this->actingAs($user)->post(route('administrators.enterprise.licenses.store', $school))->assertForbidden();
    $this->actingAs($user)->put(route('administrators.enterprise.capabilities.update', [$school, EnterpriseLicense::CAPABILITY]), ['enabled' => true])->assertForbidden();
})->with([UserRole::Registrar, UserRole::AccountingOfficer, UserRole::Student]);

it('requires a purchase before enabling a school capability', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $user->assignRole(UserRole::SuperAdmin);

    $this->actingAs($user)->put(route('administrators.enterprise.capabilities.update', [$school, EnterpriseLicense::CAPABILITY]), ['enabled' => true])
        ->assertSessionHasErrors('capability');

    $this->assertDatabaseMissing('school_capabilities', ['school_id' => $school->id, 'enabled' => true]);
});

it('activates purchased capabilities without changing installation wide module status', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $user->assignRole(UserRole::SuperAdmin);
    EnterpriseLicense::activate($school);
    $before = DB::table('module_installations')->get()->toJson();

    $this->actingAs($user)->put(route('administrators.enterprise.capabilities.update', [$school, EnterpriseLicense::CAPABILITY]), ['enabled' => false])->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('school_capabilities', ['school_id' => $school->id, 'enabled' => false, 'changed_by' => $user->id]);
    expect(DB::table('module_installations')->get()->toJson())->toBe($before);
});

it('shows only the selected schools license in marketplace props', function (): void {
    $school = School::factory()->create();
    $other = School::factory()->create();
    $user = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => $school->id]);
    $user->assignRole(UserRole::SuperAdmin);
    EnterpriseLicense::activate($school);
    app(TenantContext::class)->setCurrentSchool($other);
    config(['modules-marketplace.enabled' => false]);

    $this->actingAs($user)->get(route('administrators.module-marketplace.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('enterprise.school.id', $other->id)
        ->where('enterprise.license', null)
        ->where('enterprise.capabilities.0.licensed', false)
        ->missing('enterprise.signed_license'));
});

it('requires authentication for commercial management endpoints', function (): void {
    $school = School::factory()->create();

    $this->post(route('administrators.enterprise.licenses.store', $school))->assertRedirect();
    $this->put(route('administrators.enterprise.capabilities.update', [$school, EnterpriseLicense::CAPABILITY]), ['enabled' => true])->assertRedirect();
    $this->assertDatabaseCount('school_capabilities', 0);
});
