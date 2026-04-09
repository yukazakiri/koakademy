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
        if (! Schema::hasTable('schedule')) {
            Schema::create('schedule', function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->string('day_of_week');
                $blueprint->time('start_time');
                $blueprint->time('end_time');
                $blueprint->foreignId('room_id')->nullable()->constrained('rooms')->onDelete('set null');
                $blueprint->foreignId('class_id')->constrained('classes')->onDelete('cascade');
                $blueprint->timestamps();
                $blueprint->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule');
    }
};
