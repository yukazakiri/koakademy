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
        // Early local builds included these columns in the initial schema.
        // Apply additively to both that shape and already-installed policy data.
        foreach (['activate_at', 'retire_at'] as $column) {
            if (! Schema::hasColumn('feature_definitions', $column)) {
                Schema::table('feature_definitions', function (Blueprint $table) use ($column): void {
                    $table->timestamp($column)->nullable();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Local rollback only; production rollback preserves schedule data.
        foreach (['activate_at', 'retire_at'] as $column) {
            if (Schema::hasColumn('feature_definitions', $column)) {
                Schema::table('feature_definitions', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
