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
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('beta_access_joined_at')->nullable();
        });

        Schema::table('feature_definitions', function (Blueprint $table): void {
            $table->string('release_audience', 16)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feature_definitions', function (Blueprint $table): void {
            $table->dropColumn('release_audience');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('beta_access_joined_at');
        });
    }
};
