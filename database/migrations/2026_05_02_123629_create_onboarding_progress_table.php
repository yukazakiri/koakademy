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
        Schema::create('onboarding_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('variant', 50)->default('faculty');
            $table->json('completed_steps')->nullable();
            $table->json('checklist_state')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedSmallInteger('current_step_index')->default(0);
            $table->boolean('is_dismissed')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'variant']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('onboarding_progress');
    }
};
