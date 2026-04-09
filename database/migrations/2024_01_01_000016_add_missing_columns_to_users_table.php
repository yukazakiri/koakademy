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
        Schema::table('users', function (Blueprint $blueprint): void {
            if (! Schema::hasColumn('users', 'avatar_url')) {
                $blueprint->string('avatar_url')->nullable()->after('remember_token');
                $blueprint->string('theme_color')->nullable()->after('avatar_url');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $blueprint): void {
            if (Schema::hasColumn('users', 'avatar_url')) {
                $blueprint->dropColumn('avatar_url');
            }

            if (Schema::hasColumn('users', 'theme_color')) {
                $blueprint->dropColumn('theme_color');
            }
        });
    }
};
