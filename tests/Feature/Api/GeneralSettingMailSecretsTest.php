<?php

declare(strict_types=1);

use App\Models\GeneralSetting;
use App\Models\User;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

it('does not persist mail transport credentials through the generic settings API', function (): void {
    $user = User::factory()->create();

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

    actingAs($user, 'sanctum')
        ->postJson('/api/settings', [
            'site_name' => 'Test School',
            'currency' => 'PHP',
            'email_from_address' => 'legacy@school.example',
            'email_from_name' => 'Legacy Sender',
            'email_settings' => [
                'host' => 'smtp.school.example',
                'username' => 'legacy-user',
                'password' => 'legacy-password',
            ],
            'sequenzy_api_key' => 'legacy-provider-key',
        ])
        ->assertCreated();

    $setting = GeneralSetting::query()->sole();

    expect($setting->email_settings)->toBeNull()
        ->and($setting->currency)->toBe('PHP')
        ->and($setting->email_from_address)->toBeNull()
        ->and($setting->email_from_name)->toBeNull()
        ->and($setting->sequenzy_api_key)->toBeNull();
});
