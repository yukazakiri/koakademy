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
        if (! Schema::hasTable('shs_strands')) {
            Schema::create('shs_strands', function (Blueprint $table): void {
                $table->id();
                $table->string('strand_name');
                $table->text('description')->nullable();
                $table->foreignId('track_id')->nullable()->constrained('shs_tracks')->onDelete('set null');
                $table->timestamps();

                // Index for better performance
                $table->index('track_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shs_strands');
    }
};
