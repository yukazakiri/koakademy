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
        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();

            // Student relationship
            $table->unsignedBigInteger('student_id');
            // Foreign key constraint commented out for testing
            // $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');

            // Medical record details
            $table->string('record_type'); // 'checkup', 'vaccination', 'allergy', 'medication', 'emergency', 'dental', 'vision', 'mental_health'
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('treatment')->nullable();
            $table->text('prescription')->nullable();
            $table->text('notes')->nullable();

            // Medical professional information
            $table->string('doctor_name')->nullable();
            $table->string('clinic_name')->nullable();
            $table->string('clinic_address')->nullable();
            $table->string('doctor_contact')->nullable();

            // Dates
            $table->date('visit_date');
            $table->date('next_appointment')->nullable();
            $table->date('follow_up_date')->nullable();

            // Status and priority
            $table->enum('status', ['active', 'resolved', 'ongoing', 'cancelled'])->default('active');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->boolean('is_confidential')->default(false);

            // Health metrics (optional)
            $table->decimal('height', 5, 2)->nullable(); // in cm
            $table->decimal('weight', 5, 2)->nullable(); // in kg
            $table->integer('blood_pressure_systolic')->nullable();
            $table->integer('blood_pressure_diastolic')->nullable();
            $table->decimal('temperature', 4, 1)->nullable(); // in Celsius
            $table->integer('heart_rate')->nullable();
            $table->decimal('bmi', 4, 1)->nullable();

            // Additional data
            $table->json('vital_signs')->nullable(); // For storing additional vital signs
            $table->json('lab_results')->nullable(); // For storing lab test results
            $table->json('attachments')->nullable(); // For storing file attachments

            // Emergency contact information
            $table->boolean('emergency_contact_notified')->default(false);
            $table->timestamp('emergency_notification_sent_at')->nullable();

            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable(); // Who created this record
            $table->unsignedBigInteger('updated_by')->nullable(); // Who last updated this record
            // Foreign key constraints commented out for testing
            // $table->foreign('created_by')->references('id')->on('accounts')->onDelete('set null');
            // $table->foreign('updated_by')->references('id')->on('accounts')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            // Indexes for better performance
            $table->index(['student_id', 'record_type']);
            $table->index(['student_id', 'visit_date']);
            $table->index(['status', 'priority']);
            $table->index('visit_date');
            $table->index('is_confidential');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_records');
    }
};
