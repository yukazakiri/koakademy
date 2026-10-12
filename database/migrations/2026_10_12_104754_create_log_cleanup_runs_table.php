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
        Schema::create('log_cleanup_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32);
            $table->string('mode', 16);
            $table->string('status', 16)->default('queued');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('scheduled')->default(false);
            $table->timestamp('cutoff_at');
            $table->json('upper_ids');
            $table->unsignedBigInteger('deleted_records')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['source', 'status']);
            $table->index(['source', 'scheduled', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_cleanup_runs');
    }
};
