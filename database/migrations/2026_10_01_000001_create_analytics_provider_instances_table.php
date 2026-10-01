<?php

declare(strict_types=1);

use App\Support\Analytics\AnalyticsProviderCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the single `general_settings.analytics_provider` + `analytics_script`
 * pair with a table of provider instances, so an installation can run several
 * analytics providers at once.
 *
 * The migration backfills one row per installation from the legacy columns so
 * existing deployments keep their configuration after upgrading. The legacy
 * columns are intentionally left in place: `analytics_enabled` is still the
 * global master switch, and the old provider columns are dropped in a later
 * migration once operators have had a chance to confirm the new rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_provider_instances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('general_setting_id')->nullable()->constrained('general_settings')->cascadeOnDelete();
            $table->string('provider', 64);
            $table->string('label')->nullable();
            $table->boolean('enabled')->default(false);
            $table->json('settings')->nullable();
            $table->text('script')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['general_setting_id', 'position']);
            $table->index(['provider', 'enabled']);
        });

        $this->backfillFromLegacyColumns();
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_provider_instances');
    }

    /**
     * Convert the old single-provider configuration into instance rows.
     *
     * The legacy schema stored provider settings in a JSON blob with keys
     * prefixed by provider name (for example `umami_script_url`). Settings are
     * unpacked here into the per-instance `settings` array.
     */
    private function backfillFromLegacyColumns(): void
    {
        if (! Schema::hasTable('general_settings')) {
            return;
        }

        if (! Schema::hasColumn('general_settings', 'analytics_provider')) {
            return;
        }

        $legacySettingsAvailable = Schema::hasColumn('general_settings', 'analytics_settings');
        $legacyScriptAvailable = Schema::hasColumn('general_settings', 'analytics_script');
        $legacyGoogleIdAvailable = Schema::hasColumn('general_settings', 'google_analytics_id');

        DB::table('general_settings')
            ->select([
                'id',
                'analytics_enabled',
                'analytics_provider',
                ...($legacySettingsAvailable ? ['analytics_settings'] : []),
                ...($legacyScriptAvailable ? ['analytics_script'] : []),
                ...($legacyGoogleIdAvailable ? ['google_analytics_id'] : []),
            ])
            ->orderBy('id')
            ->each(function (object $row): void {
                $provider = is_string($row->analytics_provider ?? null) ? mb_trim($row->analytics_provider) : '';

                // A legacy value that no longer exists in the catalog (Ackee, or
                // any custom string) is preserved as a custom snippet so the
                // operator can see and re-home it rather than losing it.
                if ($provider !== '' && ! AnalyticsProviderCatalog::has($provider)) {
                    $this->insertLegacyCustomRow($row, $provider);

                    return;
                }

                if ($provider === '' && blank($row->analytics_script ?? null)) {
                    return;
                }

                $settings = $this->unpackLegacySettings($row->analytics_settings ?? null, $provider);

                // Google kept a separate `google_analytics_id` column that the
                // old form wrote alongside the JSON blob, and the old service
                // fell back to it. Preserve that fallback so installations that
                // only ever set the column are not left without an ID.
                if ($provider === 'google' && ! isset($settings['measurement_id'])) {
                    $legacyGoogleId = $row->google_analytics_id ?? null;

                    if (is_string($legacyGoogleId) && $legacyGoogleId !== '') {
                        $settings['measurement_id'] = $legacyGoogleId;
                    }
                }

                DB::table('analytics_provider_instances')->insert([
                    'general_setting_id' => $row->id,
                    'provider' => $provider !== '' ? $provider : 'custom',
                    'label' => null,
                    'enabled' => (bool) ($row->analytics_enabled ?? false),
                    'settings' => $settings === [] ? null : json_encode($settings, JSON_UNESCAPED_SLASHES),
                    'script' => filled($row->analytics_script ?? null) ? $row->analytics_script : null,
                    'position' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    /**
     * A legacy provider that the catalog no longer knows about is kept as a
     * disabled custom snippet so nothing is silently destroyed on upgrade.
     */
    private function insertLegacyCustomRow(object $row, string $provider): void
    {
        DB::table('analytics_provider_instances')->insert([
            'general_setting_id' => $row->id,
            'provider' => 'custom',
            'label' => 'Migrated from "'.$provider.'"',
            'enabled' => false,
            'settings' => null,
            'script' => filled($row->analytics_script ?? null)
                ? $row->analytics_script
                : '<!-- Koakademy: the "'.$provider.'" analytics provider is no longer available. Review and replace this snippet. -->',
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Strip the provider name prefix from legacy settings keys.
     *
     * `umami_script_url` becomes `script_url`, `openpanel_client_id` becomes
     * `client_id`, and so on, matching the catalog's field names.
     *
     * @return array<string, mixed>
     */
    private function unpackLegacySettings(mixed $raw, string $provider): array
    {
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        // A JSON column holding a JSON column (double encoded) is possible when
        // the value was written as a string rather than a structure, so unwrap
        // one more layer before giving up.
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        if (! is_array($decoded)) {
            return [];
        }

        if ($provider === 'google') {
            // The old admin form stored the measurement ID under a
            // `google_` prefix, but the catalog field is plain `measurement_id`.
            // Copying the key verbatim would leave a migrated instance enabled
            // with an empty snippet, so rename it explicitly.
            $measurementId = $decoded['google_measurement_id']
                ?? $decoded['measurement_id']
                ?? null;

            return is_string($measurementId) && $measurementId !== ''
                ? ['measurement_id' => $measurementId]
                : [];
        }

        if ($provider === '') {
            return array_filter($decoded, static fn (mixed $v): bool => $v !== null && $v !== '');
        }

        $unpacked = [];
        $prefix = $provider.'_';

        foreach ($decoded as $key => $value) {
            if (! is_string($key) || $value === null || $value === '') {
                continue;
            }

            $field = str_starts_with($key, $prefix) ? mb_substr($key, mb_strlen($prefix)) : $key;

            $unpacked[$field] = $value;
        }

        return $unpacked;
    }
};
