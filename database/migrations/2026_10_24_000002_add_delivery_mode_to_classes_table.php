<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('classes') && ! Schema::hasColumn('classes', 'delivery_mode')) {
            Schema::table('classes', function (Blueprint $table): void {
                $table->string('delivery_mode', 20)->default('face_to_face')->after('room_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('classes') && Schema::hasColumn('classes', 'delivery_mode')) {
            Schema::table('classes', function (Blueprint $table): void {
                $table->dropColumn('delivery_mode');
            });
        }
    }
};
