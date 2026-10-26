<?php

declare(strict_types=1);

use App\Services\FeatureToggleRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the AI/MCP flags introduced after the original feature bootstrap.
 *
 * Existing installations keep their current behavior by seeding the new keys
 * in legacy mode. Fresh installations already received every registered key
 * from the bootstrap migration, so this is a no-op there.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const array NEW_KEYS = [
        'ai-admin-executive',
        'mcp-server-access',
        'mcp-external-integrations',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('feature_definitions')) {
            return;
        }

        foreach (self::NEW_KEYS as $key) {
            if (FeatureToggleRegistry::classForKey($key) === null) {
                continue;
            }

            if (DB::table('feature_definitions')->where('key', $key)->exists()) {
                continue;
            }

            DB::table('feature_definitions')->insert([
                'key' => $key,
                'enabled' => true,
                'mode' => 'legacy',
                'strategy' => 'defaults',
                'percentage' => 0,
                'rollout_seed' => hash('sha256', 'koakademy:'.$key),
                'allow_opt_in' => false,
                'revision' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Removing a definition would discard an administrator's rollout
        // settings; the feature registry still references these keys.
    }
};
