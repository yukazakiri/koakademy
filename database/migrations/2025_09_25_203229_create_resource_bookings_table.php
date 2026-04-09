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
        Schema::create('resource_bookings', function (Blueprint $table): void {
            $table->id();
            $table->string('resource_type'); // room, equipment, vehicle, etc.
            $table->string('resource_name');
            $table->text('resource_description')->nullable();
            $table->string('location')->nullable();
            $table->integer('capacity')->nullable();
            $table->json('features')->nullable(); // projector, whiteboard, computers, etc.
            $table->decimal('hourly_rate', 8, 2)->nullable();
            $table->json('availability_schedule')->nullable(); // operating hours
            $table->boolean('requires_approval')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('booking_rules')->nullable(); // minimum duration, advance booking time, etc.
            $table->text('terms_and_conditions')->nullable();
            $table->foreignId('managed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['resource_type', 'is_active']);
            $table->index(['location', 'resource_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resource_bookings');
    }
};
