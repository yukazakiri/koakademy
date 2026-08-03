<?php

declare(strict_types=1);

use App\Enums\NewsletterProvider;
use App\Enums\NewsletterSubscriptionStatus;
use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\NewsletterSubscription;
use App\Models\User;
use App\Support\SystemManagementPermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

function grantNewsletterManagementPermissions(User $user, bool $update = true): void
{
    $permissions = [SystemManagementPermissions::viewPermission('newsletter')];
    if ($update) {
        $permissions[] = SystemManagementPermissions::updatePermission('newsletter');
    }

    foreach (array_filter($permissions) as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $user->givePermissionTo(array_filter($permissions));
}

function newsletterSettingsPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'enabled' => true,
        'provider' => 'sequenzy',
        'providers' => [
            'sequenzy' => ['api_key' => 'seq-marketing-secret'],
            'brevo' => ['api_key' => '', 'list_id' => ''],
            'mailchimp' => ['api_key' => '', 'server_prefix' => '', 'audience_id' => ''],
        ],
    ], $overrides);
}

beforeEach(function (): void {
    config([
        'newsletter.providers.sequenzy.url' => 'https://sequenzy.test/api/v1',
        'newsletter.providers.brevo.url' => 'https://brevo.test/v3',
        'newsletter.providers.mailchimp.url' => 'https://{server}.mailchimp.test/3.0',
    ]);
});

it('encrypts newsletter credentials and redacts every secret from Inertia', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    grantNewsletterManagementPermissions($user);
    Http::fake(['https://sequenzy.test/api/v1/subscribers/*' => Http::response([], 404)]);

    actingAs($user)
        ->put(portalUrlForAdministrators('/administrators/system-management/newsletter'), newsletterSettingsPayload())
        ->assertRedirect()
        ->assertSessionHas('success');

    $settings = GeneralSetting::query()->firstOrFail();
    $raw = DB::table('general_settings')->where('id', $settings->id)->value('newsletter_settings');
    expect(data_get($settings->newsletter_settings, 'providers.sequenzy.api_key'))->toBe('seq-marketing-secret')
        ->and($raw)->toBeString()
        ->and($raw)->not->toContain('seq-marketing-secret');

    actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/system-management/newsletter'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/system-management/newsletter', false)
            ->where('newsletter_config.enabled', true)
            ->where('newsletter_config.provider', 'sequenzy')
            ->where('newsletter_config.providers.sequenzy.configured', true)
            ->missing('newsletter_config.providers.sequenzy.api_key')
            ->missing('general_settings.newsletter_settings'));
});

it('preserves a blank secret and disables without checking connectivity', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    grantNewsletterManagementPermissions($user);
    $settings = GeneralSetting::query()->firstOrCreate([], ['site_name' => 'Test']);
    $settings->update(['newsletter_settings' => newsletterSettingsPayload()]);
    Http::preventStrayRequests();

    actingAs($user)
        ->put(portalUrlForAdministrators('/administrators/system-management/newsletter'), newsletterSettingsPayload([
            'enabled' => false,
            'providers' => ['sequenzy' => ['api_key' => '']],
        ]))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(data_get($settings->refresh()->newsletter_settings, 'enabled'))->toBeFalse()
        ->and(data_get($settings->newsletter_settings, 'providers.sequenzy.api_key'))->toBe('seq-marketing-secret');
});

it('allows a partially configured provider to be disabled', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    grantNewsletterManagementPermissions($user);
    Http::preventStrayRequests();

    actingAs($user)
        ->put(portalUrlForAdministrators('/administrators/system-management/newsletter'), newsletterSettingsPayload([
            'enabled' => false,
            'provider' => 'mailchimp',
            'providers' => ['mailchimp' => ['api_key' => '', 'server_prefix' => '', 'audience_id' => '']],
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    expect((bool) data_get(GeneralSetting::query()->firstOrFail()->newsletter_settings, 'enabled'))->toBeFalse();
});

it('requires a valid live connection before enabling', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    grantNewsletterManagementPermissions($user);
    Http::fake(['https://sequenzy.test/api/v1/subscribers/*' => Http::response([], 401)]);

    actingAs($user)
        ->from(portalUrlForAdministrators('/administrators/system-management/newsletter'))
        ->put(portalUrlForAdministrators('/administrators/system-management/newsletter'), newsletterSettingsPayload())
        ->assertRedirect()
        ->assertSessionHasErrors('provider');

    expect((bool) data_get(GeneralSetting::query()->first()?->newsletter_settings, 'enabled', false))->toBeFalse();
});

it('validates provider-specific destination fields', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    grantNewsletterManagementPermissions($user);

    actingAs($user)
        ->put(portalUrlForAdministrators('/administrators/system-management/newsletter'), newsletterSettingsPayload([
            'provider' => 'mailchimp',
            'providers' => ['mailchimp' => ['api_key' => 'key', 'server_prefix' => '', 'audience_id' => '']],
        ]))
        ->assertSessionHasErrors([
            'providers.mailchimp.server_prefix',
            'providers.mailchimp.audience_id',
        ]);
});

it('switches providers for future signups without modifying existing records', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    grantNewsletterManagementPermissions($user);
    $student = User::factory()->create(['role' => UserRole::Student]);
    $subscription = NewsletterSubscription::query()->create([
        'user_id' => $student->id,
        'email' => $student->email,
        'provider' => NewsletterProvider::Sequenzy,
        'status' => NewsletterSubscriptionStatus::Subscribed,
        'subscribed_at' => now(),
    ]);
    $settings = GeneralSetting::query()->firstOrCreate([], ['site_name' => 'Test']);
    $settings->update(['newsletter_settings' => newsletterSettingsPayload(['enabled' => false])]);
    Http::preventStrayRequests();

    actingAs($user)
        ->put(portalUrlForAdministrators('/administrators/system-management/newsletter'), newsletterSettingsPayload([
            'enabled' => false,
            'provider' => 'brevo',
            'providers' => ['brevo' => ['api_key' => 'brevo-secret', 'list_id' => '42']],
        ]))
        ->assertRedirect()
        ->assertSessionHas('warning');

    expect($subscription->refresh()->provider)->toBe(NewsletterProvider::Sequenzy)
        ->and(NewsletterSubscription::query()->count())->toBe(1)
        ->and(data_get($settings->refresh()->newsletter_settings, 'provider'))->toBe('brevo');
});

it('enforces view and update permissions independently', function (): void {
    $viewer = User::factory()->create(['role' => UserRole::Admin]);
    grantNewsletterManagementPermissions($viewer, false);

    actingAs($viewer)
        ->get(portalUrlForAdministrators('/administrators/system-management/newsletter'))
        ->assertOk();

    actingAs($viewer)
        ->put(portalUrlForAdministrators('/administrators/system-management/newsletter'), newsletterSettingsPayload(['enabled' => false]))
        ->assertForbidden();
});
