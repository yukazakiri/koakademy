<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the attribute_changes column required by
     * spatie/laravel-activitylog v5.
     */
    public function up(): void
    {
        Schema::table('activity_log', static function (Blueprint $table): void {
            $table->json('attribute_changes')->nullable()->after('event');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_log', static function (Blueprint $table): void {
            $table->dropColumn('attribute_changes');
        });
    }
};
