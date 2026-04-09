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
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('event'); // event, academic_calendar, resource_booking
            $table->string('category')->nullable(); // academic, extracurricular, administrative, etc.
            $table->string('location')->nullable();
            $table->datetime('start_datetime');
            $table->datetime('end_datetime');
            $table->boolean('is_all_day')->default(false);
            $table->string('recurrence_type')->nullable(); // none, daily, weekly, monthly, yearly
            $table->json('recurrence_data')->nullable(); // store recurrence rules
            $table->datetime('recurrence_end_date')->nullable();
            $table->integer('max_attendees')->nullable();
            $table->boolean('requires_rsvp')->default(false);
            $table->boolean('allow_guests')->default(true);
            $table->string('status')->default('active'); // active, cancelled, postponed, completed
            $table->string('visibility')->default('public'); // public, private, internal
            $table->json('custom_fields')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['start_datetime', 'end_datetime']);
            $table->index(['type', 'status']);
            $table->index(['created_by', 'start_datetime']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
