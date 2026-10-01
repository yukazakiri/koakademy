<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AnalyticsProviderInstance;
use App\Models\GeneralSetting;
use App\Models\User;
use App\Support\Analytics\AnalyticsProviderCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The analytics settings screen manages a list of provider instances, so these
 * cover adding, editing, removing, and rendering several at once.
 */
final class AnalyticsProviderInstancesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private GeneralSetting $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin]);

        foreach (['View:SystemManagementAnalytics', 'Update:SystemManagementAnalytics'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $this->admin->givePermissionTo(['View:SystemManagementAnalytics', 'Update:SystemManagementAnalytics']);

        $this->settings = GeneralSetting::factory()->create([
            'analytics_enabled' => true,
        ]);
    }

    public function test_it_renders_the_catalog_of_supported_providers(): void
    {
        $response = $this->actingAs($this->admin)->get(route('administrators.system-management.analytics.index'));

        $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/system-management/analytics', false)
            ->has('analytics_catalog')
            ->has('analytics_providers')
        );

        $catalog = $response->viewData('page')['props']['analytics_catalog'];

        $this->assertIsArray($catalog);
        $this->assertGreaterThanOrEqual(10, count($catalog));

        foreach ($catalog as $provider) {
            $this->assertArrayHasKey('key', $provider);
            $this->assertArrayHasKey('label', $provider);
            $this->assertArrayHasKey('description', $provider);
            $this->assertArrayHasKey('fields', $provider);
        }

        // Every catalog provider must be self-describing enough for the admin
        // UI to render its form without a bespoke screen.
        $this->assertContains('umami', array_column($catalog, 'key'));
        $this->assertContains('posthog', array_column($catalog, 'key'));
        $this->assertContains('matomo', array_column($catalog, 'key'));
    }

    public function test_it_saves_multiple_providers_at_once(): void
    {
        $response = $this->actingAs($this->admin)->put(route('administrators.system-management.analytics.update'), [
            'analytics_enabled' => true,
            'providers' => [
                [
                    'provider' => 'umami',
                    'enabled' => true,
                    'settings' => [
                        'script_url' => 'https://umami.example.com/script.js',
                        'website_id' => 'umami-website',
                        'heatmaps' => true,
                    ],
                ],
                [
                    'provider' => 'plausible',
                    'enabled' => true,
                    'settings' => [
                        'script_url' => 'https://plausible.example.com/js/script.js',
                        'domain' => 'portal.example.edu',
                    ],
                ],
            ],
        ]);

        $response->assertRedirect();

        $this->assertSame(2, AnalyticsProviderInstance::query()->count());

        $markup = app(\App\Services\AnalyticsSettingsService::class)->renderHeadMarkup();

        // Both snippets render together, proving providers are additive.
        $this->assertStringContainsString('umami.example.com/script.js', $markup);
        $this->assertStringContainsString('plausible.example.com/js/script.js', $markup);
        $this->assertStringContainsString('portal.example.edu', $markup);
    }

    public function test_it_supports_several_instances_of_the_same_provider(): void
    {
        $this->actingAs($this->admin)->put(route('administrators.system-management.analytics.update'), [
            'analytics_enabled' => true,
            'providers' => [
                [
                    'provider' => 'umami',
                    'label' => 'Portal site',
                    'enabled' => true,
                    'settings' => ['script_url' => 'https://umami.example.com/script.js', 'website_id' => 'portal'],
                ],
                [
                    'provider' => 'umami',
                    'label' => 'Admin site',
                    'enabled' => true,
                    'settings' => ['script_url' => 'https://umami.example.com/script.js', 'website_id' => 'admin'],
                ],
            ],
        ]);

        $this->assertSame(2, AnalyticsProviderInstance::query()->where('provider', 'umami')->count());

        $markup = app(\App\Services\AnalyticsSettingsService::class)->renderHeadMarkup();

        $this->assertStringContainsString('portal', $markup);
        $this->assertStringContainsString('admin', $markup);
    }

    public function test_disabled_providers_are_not_rendered(): void
    {
        $this->makeInstance(['enabled' => false]);

        $this->assertSame('', app(\App\Services\AnalyticsSettingsService::class)->renderHeadMarkup());
    }

    public function test_the_global_switch_suppresses_every_provider(): void
    {
        $this->makeInstance();

        $this->settings->update(['analytics_enabled' => false]);

        $this->assertSame('', app(\App\Services\AnalyticsSettingsService::class)->renderHeadMarkup());
    }

    public function test_it_updates_an_existing_instance_and_deletes_removed_ones(): void
    {
        $keep = $this->makeInstance();
        $remove = $this->makeInstance(['provider' => 'goatcounter', 'settings' => ['script_url' => 'https://gc.example.com/count.js', 'site_code' => 'code']]);

        $this->actingAs($this->admin)->put(route('administrators.system-management.analytics.update'), [
            'analytics_enabled' => true,
            'providers' => [
                [
                    'id' => $keep->id,
                    'provider' => 'umami',
                    'label' => 'Renamed',
                    'enabled' => false,
                    'settings' => ['script_url' => 'https://umami.example.com/script.js', 'website_id' => 'site-123'],
                ],
            ],
        ]);

        $this->assertSame(1, AnalyticsProviderInstance::query()->count());
        $this->assertDatabaseMissing('analytics_provider_instances', ['id' => $remove->id]);

        $keep->refresh();
        $this->assertSame('Renamed', $keep->label);
        $this->assertFalse($keep->enabled);
    }

    public function test_it_rejects_a_provider_that_is_not_in_the_catalog(): void
    {
        $this->actingAs($this->admin)
            ->put(route('administrators.system-management.analytics.update'), [
                'analytics_enabled' => true,
                'providers' => [
                    ['provider' => 'not-a-real-provider', 'enabled' => true, 'settings' => []],
                ],
            ])
            ->assertSessionHasErrors('providers.0.provider');

        $this->assertSame(0, AnalyticsProviderInstance::query()->count());
    }

    public function test_umami_heatmaps_load_the_recorder_script(): void
    {
        $this->makeInstance([
            'settings' => [
                'script_url' => 'https://umami.example.com/script.js',
                'website_id' => 'site-123',
                'heatmaps' => true,
            ],
        ]);

        $markup = app(\App\Services\AnalyticsSettingsService::class)->renderHeadMarkup();

        $this->assertStringContainsString('script.js', $markup);
        $this->assertStringContainsString('recorder.js', $markup);
    }

    public function test_a_manual_snippet_overrides_the_generated_one(): void
    {
        $this->makeInstance(['script' => '<script>/* custom */</script>']);

        $markup = app(\App\Services\AnalyticsSettingsService::class)->renderHeadMarkup();

        $this->assertStringContainsString('/* custom */', $markup);
        $this->assertStringNotContainsString('script.js', $markup);
    }

    public function test_google_analytics_is_flagged_for_client_side_page_views(): void
    {
        $this->makeInstance([
            'provider' => 'google',
            'settings' => ['measurement_id' => 'G-ABC123'],
        ]);

        $frontend = app(\App\Services\AnalyticsSettingsService::class)->getFrontendConfig();

        $this->assertCount(1, $frontend['providers']);
        $this->assertTrue($frontend['providers'][0]['session']);
    }

    public function test_every_catalog_provider_builds_a_snippet_when_configured(): void
    {
        foreach (AnalyticsProviderCatalog::all() as $key => $definition) {
            $values = [];

            foreach ($definition['fields'] as $field) {
                $values[$field['key']] = match ($field['type']) {
                    'url' => 'https://example.com/x.js',
                    'textarea' => '<script>/* x */</script>',
                    'toggle' => true,
                    'select' => $field['options'][0]['value'] ?? 'x',
                    default => 'x',
                };
            }

            $snippet = AnalyticsProviderCatalog::buildSnippet($key, $values);

            $this->assertNotSame('', $snippet, "Provider [{$key}] produced an empty snippet when fully configured.");
        }
    }

    public function test_providers_are_inert_when_not_fully_configured(): void
    {
        foreach (AnalyticsProviderCatalog::keys() as $key) {
            $this->assertSame('', AnalyticsProviderCatalog::buildSnippet($key, []), "Provider [{$key}] emitted a snippet with no settings.");
        }
    }

    private function makeInstance(array $attributes = []): AnalyticsProviderInstance
    {
        return AnalyticsProviderInstance::query()->create(array_merge([
            'general_setting_id' => $this->settings->id,
            'provider' => 'umami',
            'enabled' => true,
            'settings' => [
                'script_url' => 'https://umami.example.com/script.js',
                'website_id' => 'site-123',
            ],
        ], $attributes));
    }
}
