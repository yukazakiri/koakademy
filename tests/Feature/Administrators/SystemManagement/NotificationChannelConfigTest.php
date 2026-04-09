<?php

declare(strict_types=1);

use App\Enums\NotificationChannel;
use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\User;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

function grantNotificationManagementPermission(User $user): void
{
    foreach (['View:SystemManagementNotifications', 'Update:SystemManagementNotifications'] as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $user->givePermissionTo(['View:SystemManagementNotifications', 'Update:SystemManagementNotifications']);
}

it('saves notification channel configuration to more_configs', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantNotificationManagementPermission($user);

    $settings = GeneralSetting::query()->first();
    if (! $settings) {
        $settings = GeneralSetting::create(['site_name' => 'Test']);
    }

    actingAs($user)
        ->put(portalUrlForAdministrators('/administrators/system-management/notifications'), [
            'enabled_channels' => ['mail', 'database', 'broadcast'],
            'pusher' => [
                'app_id' => '111222',
                'key' => 'test-key',
                'secret' => 'test-secret',
                'cluster' => 'ap1',
            ],
            'sms' => [
                'provider' => '',
                'api_key' => '',
                'sender_id' => '',
            ],
        ])
        ->assertRedirect();

    $settings->refresh();
    $channelConfig = $settings->more_configs['notification_channels'] ?? null;

    expect($channelConfig)->not->toBeNull()
        ->and($channelConfig['enabled_channels'])->toContain('mail', 'database', 'broadcast')
        ->and($channelConfig['pusher']['app_id'])->toBe('111222')
        ->and($channelConfig['pusher']['cluster'])->toBe('ap1');
});

it('rejects invalid channel values', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantNotificationManagementPermission($user);

    actingAs($user)
        ->put(portalUrlForAdministrators('/administrators/system-management/notifications'), [
            'enabled_channels' => ['invalid_channel'],
            'pusher' => [],
            'sms' => [],
        ])
        ->assertSessionHasErrors('enabled_channels.0');
});

it('has all expected notification channel enum cases', function (): void {
    expect(NotificationChannel::values())->toContain('mail', 'database', 'broadcast', 'sms', 'pusher')
        ->and(NotificationChannel::defaultChannels())->toHaveCount(2)
        ->and(NotificationChannel::Mail->getLabel())->toBe('Email')
        ->and(NotificationChannel::Database->getLabel())->toBe('In-App (Database)')
        ->and(NotificationChannel::Broadcast->isRealtime())->toBeTrue()
        ->and(NotificationChannel::Mail->isRealtime())->toBeFalse();
});
