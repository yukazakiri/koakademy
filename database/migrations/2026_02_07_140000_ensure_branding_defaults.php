<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Run the BrandingSettingsSeeder to ensure defaults are populated
        // This is a temporary measure to ensure production deployments get the default values
        // immediately upon migration.
        try {
            Artisan::call('db:seed', [
                '--class' => Database\Seeders\BrandingSettingsSeeder::class,
                '--force' => true,
            ]);
        } catch (Throwable $e) {
            // Log error but allow migration to proceed if seeder fails (e.g. if already seeded)
            Illuminate\Support\Facades\Log::warning('Could not run BrandingSettingsSeeder from migration: '.$e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reversion needed
    }
};
