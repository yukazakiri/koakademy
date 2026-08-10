<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Mail\SequenzyApiKeyResolver;
use App\Models\GeneralSetting;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

function grantMailStatusPermission(User $user): void
{
    $permission = Permission::firstOrCreate([
        'name' => 'View:SystemManagementMail',
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo($permission);
}

it('shows deployment-managed runtime mail status without legacy credentials', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    grantMailStatusPermission($user);
    $settings = GeneralSetting::query()->firstOrCreate([], ['site_name' => 'Test']);
    $settings->update([
        'sequenzy_api_key' => 'legacy-database-key',
        'email_settings' => [
            'host' => 'legacy.smtp.example',
            'username' => 'legacy-user',
            'password' => 'legacy-password',
        ],
    ]);
    config([
        'mail.default' => 'smtp',
        'mail.from.address' => 'no-reply@school.example',
        'mail.from.name' => 'KoAkademy',
    ]);

    actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/system-management/mail'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/system-management/mail', false)
            ->where('mail_config.driver', 'smtp')
            ->where('mail_config.email_from_address', 'no-reply@school.example')
            ->where('mail_config.email_from_name', 'KoAkademy')
            ->where('mail_config.managed_by', 'deployment')
            ->missing('mail_config.host')
            ->missing('mail_config.username')
            ->missing('mail_config.password')
            ->missing('general_settings.sequenzy_api_key')
            ->missing('general_settings.email_settings.password'));
});

it('does not expose web routes that can change deployment mail settings', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    grantMailStatusPermission($user);

    actingAs($user)
        ->put(portalUrlForAdministrators('/administrators/system-management/mail'), [])
        ->assertMethodNotAllowed();

    actingAs($user)
        ->post(portalUrlForAdministrators('/administrators/system-management/mail/test'), [])
        ->assertNotFound();
});

it('resolves Sequenzy credentials from deployment configuration and ignores the database', function (): void {
    config([
        'services.sequenzy.key' => 'deployment-key',
        'services.sequenzy.legacy_key' => 'legacy-environment-key',
    ]);
    GeneralSetting::query()->firstOrCreate([], ['site_name' => 'Test'])->update([
        'sequenzy_api_key' => 'database-key',
    ]);

    expect(app(SequenzyApiKeyResolver::class)->resolve())->toBe('deployment-key');
});
