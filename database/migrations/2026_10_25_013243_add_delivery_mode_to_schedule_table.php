<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('schedule', function (Blueprint $table): void {
            $table->string('delivery_mode', 20)->default('face_to_face')->after('room_id');
        });

        // Preserve the meaning of meetings created before delivery was set per meeting.
        DB::table('schedule')->whereIn('class_id', DB::table('classes')->select('id')->where('delivery_mode', 'online'))
            ->update(['delivery_mode' => 'online', 'room_id' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedule', function (Blueprint $table): void {
            $table->dropColumn('delivery_mode');
        });
    }
};
