<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropColumn('data');
            });
            Schema::table('notifications', function (Blueprint $table) {
                $table->json('data')->nullable();
            });
        } else {
            Schema::table('notifications', function (Blueprint $table) {
                $table->jsonb('data')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropColumn('data');
            });
            Schema::table('notifications', function (Blueprint $table) {
                $table->text('data')->nullable();
            });
        } else {
            Schema::table('notifications', function (Blueprint $table) {
                $table->text('data')->change();
            });
        }
    }
};
