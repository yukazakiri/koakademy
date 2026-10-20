<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * The settings API manages the school's global configuration. It used to call
 * SoftDeletes methods on a model without the trait, so the restore and
 * force-delete endpoints always failed with a 500, and the delete endpoint
 * dropped the row for good. On top of that the whole group was reachable with
 * any authenticated token.
 */
function generalSettingApiManager(): User
{
    $user = User::factory()->create(['role' => UserRole::Admin]);

    foreach (['View:SystemManagementSchool', 'Update:SystemManagementSchool'] as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $user->givePermissionTo([
        'View:SystemManagementSchool',
        'Update:SystemManagementSchool',
    ]);

    Sanctum::actingAs($user);

    return $user;
}

function generalSettingApiStudent(): User
{
    $user = User::factory()->create(['role' => UserRole::Student]);

    Sanctum::actingAs($user);

    return $user;
}

it('soft deletes the configuration instead of dropping the row', function (): void {
    generalSettingApiManager();

    $settings = GeneralSetting::factory()->create();

    $response = $this->deleteJson("/api/settings/{$settings->id}");

    $response->assertOk()
        ->assertJson([
            'message' => 'General settings deleted successfully',
        ]);

    // The row survives so the school can put its configuration back.
    $this->assertSoftDeleted('general_settings', [
        'id' => $settings->id,
    ]);

    $this->assertDatabaseHas('general_settings', [
        'id' => $settings->id,
    ]);
});

it('hides a deleted configuration from the default listing', function (): void {
    generalSettingApiManager();

    $deleted = GeneralSetting::factory()->create();
    $active = GeneralSetting::factory()->create();

    $this->deleteJson("/api/settings/{$deleted->id}")->assertOk();

    $this->getJson('/api/settings')
        ->assertOk()
        ->assertJsonFragment(['id' => $active->id])
        ->assertJsonMissing(['id' => $deleted->id]);

    $this->getJson('/api/settings?with_trashed=1')
        ->assertOk()
        ->assertJsonFragment(['id' => $deleted->id]);
});

it('restores a deleted configuration', function (): void {
    generalSettingApiManager();

    $settings = GeneralSetting::factory()->create();

    $this->deleteJson("/api/settings/{$settings->id}")->assertOk();

    $response = $this->postJson("/api/settings/{$settings->id}/restore");

    $response->assertOk()
        ->assertJson([
            'message' => 'General settings restored successfully',
            'data' => [
                'id' => $settings->id,
            ],
        ]);

    $this->assertDatabaseHas('general_settings', [
        'id' => $settings->id,
        'deleted_at' => null,
    ]);
});

it('permanently deletes a configuration on request', function (): void {
    generalSettingApiManager();

    $settings = GeneralSetting::factory()->create();

    $this->deleteJson("/api/settings/{$settings->id}")->assertOk();

    $response = $this->deleteJson("/api/settings/{$settings->id}/force");

    $response->assertOk()
        ->assertJson([
            'message' => 'General settings permanently deleted',
        ]);

    $this->assertDatabaseMissing('general_settings', [
        'id' => $settings->id,
    ]);
});

it('returns not found when restoring a configuration that is not deleted', function (): void {
    generalSettingApiManager();

    $settings = GeneralSetting::factory()->create();

    $response = $this->postJson("/api/settings/{$settings->id}/restore");

    $response->assertNotFound()
        ->assertJson([
            'message' => 'General settings not found',
        ]);

    $this->assertDatabaseHas('general_settings', [
        'id' => $settings->id,
        'deleted_at' => null,
    ]);
});

it('returns not found when restoring or force deleting a missing configuration', function (): void {
    generalSettingApiManager();

    $this->postJson('/api/settings/999999/restore')
        ->assertNotFound()
        ->assertJson([
            'message' => 'General settings not found',
        ]);

    $this->deleteJson('/api/settings/999999/force')
        ->assertNotFound()
        ->assertJson([
            'message' => 'General settings not found',
        ]);
});

it('returns not found when deleting a missing configuration', function (): void {
    generalSettingApiManager();

    $this->deleteJson('/api/settings/999999')
        ->assertNotFound()
        ->assertJson([
            'message' => 'General settings not found',
        ]);
});

it('drops the cached configuration when it is deleted', function (): void {
    generalSettingApiManager();

    $settings = GeneralSetting::factory()->create(['site_name' => 'Live School']);

    Cache::forever('general_settings_id', $settings->id);

    $this->deleteJson("/api/settings/{$settings->id}")->assertOk();

    expect(Cache::get('general_settings_id'))->toBeNull();
});

it('refuses to let a student delete the school configuration', function (): void {
    generalSettingApiStudent();

    $settings = GeneralSetting::factory()->create();

    $this->deleteJson("/api/settings/{$settings->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('general_settings', [
        'id' => $settings->id,
        'deleted_at' => null,
    ]);
});

it('refuses to let a student restore or force delete the school configuration', function (): void {
    generalSettingApiManager();

    $settings = GeneralSetting::factory()->create();

    $this->deleteJson("/api/settings/{$settings->id}")->assertOk();

    generalSettingApiStudent();

    $this->postJson("/api/settings/{$settings->id}/restore")
        ->assertForbidden();

    $this->deleteJson("/api/settings/{$settings->id}/force")
        ->assertForbidden();

    $this->assertSoftDeleted('general_settings', [
        'id' => $settings->id,
    ]);
});

it('refuses to let a student rewrite the school configuration', function (): void {
    generalSettingApiStudent();

    $settings = GeneralSetting::factory()->create(['site_name' => 'Original Name']);

    $this->putJson("/api/settings/{$settings->id}", [
        'site_name' => 'Hijacked Name',
    ])->assertForbidden();

    $this->assertDatabaseHas('general_settings', [
        'id' => $settings->id,
        'site_name' => 'Original Name',
    ]);
});

it('refuses to let a student read the school configuration', function (): void {
    generalSettingApiStudent();

    GeneralSetting::factory()->create();

    $this->getJson('/api/settings')->assertForbidden();
    $this->getJson('/api/settings/current')->assertForbidden();
    $this->getJson('/api/settings/key/site_name')->assertForbidden();
    $this->getJson('/api/settings/global')->assertForbidden();
});

it('keeps the per-user preference endpoints available to every account', function (): void {
    generalSettingApiStudent();

    $this->getJson('/api/settings/user/preferences')
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'current_semester',
                'current_school_year_start',
                'current_school_year_string',
            ],
        ]);
});
