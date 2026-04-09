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
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('resource_booking_id')->constrained('resource_bookings')->onDelete('cascade');
            $table->foreignId('booked_by')->constrained('users')->onDelete('cascade');
            $table->datetime('start_datetime');
            $table->datetime('end_datetime');
            $table->string('purpose');
            $table->text('notes')->nullable();
            $table->integer('expected_attendees')->nullable();
            $table->string('status')->default('pending'); // pending, approved, confirmed, cancelled, completed
            $table->decimal('total_cost', 10, 2)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->datetime('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->datetime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->json('additional_requirements')->nullable();
            $table->timestamps();

            $table->index(['resource_booking_id', 'start_datetime', 'end_datetime']);
            $table->index(['booked_by', 'status']);
            $table->index(['status', 'start_datetime']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
