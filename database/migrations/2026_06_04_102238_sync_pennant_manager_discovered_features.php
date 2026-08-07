<?php

declare(strict_types=1);

use App\Filament\Plugins\PennantManager\Services\FeatureDiscovery;
use App\Filament\Plugins\PennantManager\Support\FeatureValue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('features')) {
            return;
        }

        $now = now();

        foreach (FeatureDiscovery::discover() as $featureName) {
            DB::table('features')->insertOrIgnore([
                'name' => $featureName,
                'scope' => '__laravel_null',
                'value' => FeatureValue::encode(enabled: false),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Keep feature rows because they may have been edited in production.
    }
};
