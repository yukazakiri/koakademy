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
        if (Schema::hasTable('general_settings') && ! Schema::hasColumn('general_settings', 'is_setup')) {
            Schema::table('general_settings', function (Blueprint $table): void {
                $table->boolean('is_setup')->default(false);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('general_settings') && Schema::hasColumn('general_settings', 'is_setup')) {
            Schema::table('general_settings', function (Blueprint $table): void {
                $table->dropColumn('is_setup');
            });
        }
    }
};
