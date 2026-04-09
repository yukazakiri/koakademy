<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('general_settings', function (Blueprint $table): void {
            $table->boolean('analytics_enabled')
                ->default(false)
                ->after('posthog_html_snippet');
            $table->string('analytics_provider')
                ->nullable()
                ->after('analytics_enabled');
            $table->text('analytics_script')
                ->nullable()
                ->after('analytics_provider');
            $table->json('analytics_settings')
                ->nullable()
                ->after('analytics_script');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'analytics_enabled',
                'analytics_provider',
                'analytics_script',
                'analytics_settings',
            ]);
        });
    }
};
