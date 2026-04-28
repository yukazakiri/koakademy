<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('notifications', function (Illuminate\Database\Schema\Blueprint $table): void {
                $table->dropColumn('data');
            });
            Schema::table('notifications', function (Illuminate\Database\Schema\Blueprint $table): void {
                $table->json('data')->nullable();
            });
        } else {
            DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE jsonb USING data::jsonb');
            DB::statement('ALTER TABLE notifications ALTER COLUMN data SET NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('notifications', function (Illuminate\Database\Schema\Blueprint $table): void {
                $table->dropColumn('data');
            });
            Schema::table('notifications', function (Illuminate\Database\Schema\Blueprint $table): void {
                $table->text('data')->nullable();
            });
        } else {
            DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text USING data::text');
            DB::statement('ALTER TABLE notifications ALTER COLUMN data DROP NOT NULL');
        }
    }
};
