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
        Schema::create('event_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('reminder_type'); // email, push, sms, in_app
            $table->integer('minutes_before'); // minutes before event to send reminder
            $table->string('status')->default('pending'); // pending, sent, failed
            $table->datetime('scheduled_at');
            $table->datetime('sent_at')->nullable();
            $table->text('message')->nullable();
            $table->json('delivery_data')->nullable(); // for tracking delivery
            $table->text('failure_reason')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'scheduled_at']);
            $table->index(['user_id', 'status']);
            $table->index(['scheduled_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_reminders');
    }
};
