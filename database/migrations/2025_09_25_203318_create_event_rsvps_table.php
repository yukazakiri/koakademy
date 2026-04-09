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
        Schema::create('event_rsvps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('response')->default('pending'); // pending, attending, not_attending, maybe
            $table->integer('guest_count')->default(0);
            $table->text('dietary_requirements')->nullable();
            $table->text('special_requests')->nullable();
            $table->text('notes')->nullable();
            $table->datetime('responded_at')->nullable();
            $table->boolean('checked_in')->default(false);
            $table->datetime('checked_in_at')->nullable();
            $table->json('custom_responses')->nullable(); // for custom RSVP questions
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
            $table->index(['event_id', 'response']);
            $table->index(['user_id', 'response']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_rsvps');
    }
};
