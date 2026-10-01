<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AnalyticsProviderInstance;
use App\Models\GeneralSetting;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Upgrading an existing installation must not lose its analytics setup, and a
 * provider that no longer exists must be preserved rather than dropped.
 */
final class AnalyticsProviderMigrationTest extends TestCase
{
    public function test_it_backfills_a_umami_configuration_from_the_legacy_columns(): void
    {
        $this->withoutAnalyticsInstanceTable();

        GeneralSetting::factory()->create([
            'analytics_enabled' => true,
            'analytics_provider' => 'umami',
            'analytics_settings' => json_encode([
                'umami_script_url' => 'https://umami.example.com/script.js',
                'umami_website_id' => 'legacy-website',
                'umami_host_url' => 'https://umami.example.com',
            ]),
        ]);

        $this->runBackfill();

        $instance = AnalyticsProviderInstance::query()->sole();

        $this->assertSame('umami', $instance->provider);
        $this->assertTrue($instance->enabled);
        $this->assertSame([
            'script_url' => 'https://umami.example.com/script.js',
            'website_id' => 'legacy-website',
            'host_url' => 'https://umami.example.com',
        ], $instance->settings);
    }

    public function test_it_preserves_a_removed_provider_as_a_disabled_custom_snippet(): void
    {
        $this->withoutAnalyticsInstanceTable();

        GeneralSetting::factory()->create([
            'analytics_enabled' => true,
            'analytics_provider' => 'ackee',
            'analytics_settings' => json_encode(['ackee_domain_id' => 'abc-123']),
        ]);

        $this->runBackfill();

        $instance = AnalyticsProviderInstance::query()->sole();

        $this->assertSame('custom', $instance->provider);
        $this->assertStringContainsString('ackee', (string) $instance->label);
        $this->assertStringContainsString('no longer available', (string) $instance->script);
        // Disabled so a removed provider can never start reporting by accident.
        $this->assertFalse($instance->enabled);
    }

    public function test_it_keeps_a_manual_script_override(): void
    {
        $this->withoutAnalyticsInstanceTable();

        GeneralSetting::factory()->create([
            'analytics_enabled' => true,
            'analytics_provider' => 'plausible',
            'analytics_script' => '<script>/* manual */</script>',
        ]);

        $this->runBackfill();

        $this->assertSame('<script>/* manual */</script>', AnalyticsProviderInstance::query()->sole()->script);
    }

    public function test_it_skips_installations_with_no_provider_configured(): void
    {
        $this->withoutAnalyticsInstanceTable();

        GeneralSetting::factory()->create([
            'analytics_enabled' => false,
            'analytics_provider' => null,
            'analytics_script' => null,
        ]);

        $this->runBackfill();

        $this->assertSame(0, AnalyticsProviderInstance::query()->count());
    }

    /**
     * The migration is skipped when the table already exists, so drop it to
     * replay the backfill against legacy data.
     */
    private function withoutAnalyticsInstanceTable(): void
    {
        Schema::dropIfExists('analytics_provider_instances');
    }

    /**
     * Replay the migration's up() against the legacy data.
     *
     * The schema is recreated inside up(), which is what we want: the backfill
     * only makes sense against a freshly created table.
     */
    private function runBackfill(): void
    {
        $migration = require __DIR__.'/../../database/migrations/2026_10_01_000001_create_analytics_provider_instances_table.php';

        $migration->up();

        $this->assertTrue(Schema::hasTable('analytics_provider_instances'));
    }
}
