<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('classes') && Schema::hasColumn('classes', 'delivery_mode')) {
            Schema::table('classes', function (Blueprint $table): void {
                $table->string('delivery_mode', 32)->default('face_to_face')->change();
            });
        }

        if (Schema::hasTable('schedule') && Schema::hasColumn('schedule', 'delivery_mode')) {
            Schema::table('schedule', function (Blueprint $table): void {
                $table->string('delivery_mode', 32)->default('face_to_face')->change();
            });
        }
    }

    public function down(): void
    {
        // Keep varchar(32) on rollback to prevent truncating online_synchronous values.
    }
};
