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
        Schema::create('class_attendance_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained('schedule')->nullOnDelete();
            $table->date('session_date');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->uuid('taken_by')->nullable();
            $table->foreign('taken_by')->references('id')->on('faculty')->nullOnDelete();
            $table->string('topic')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('class_attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_attendance_session_id')->constrained('class_attendance_sessions')->cascadeOnDelete();
            $table->foreignId('class_enrollment_id')->constrained('class_enrollments')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('status', 24)->default('present');
            $table->text('remarks')->nullable();
            $table->uuid('marked_by')->nullable();
            $table->foreign('marked_by')->references('id')->on('faculty')->nullOnDelete();
            $table->timestamp('marked_at')->nullable();
            $table->timestamps();

            $table->unique(['class_attendance_session_id', 'class_enrollment_id'], 'attendance_session_enrollment_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_attendance_records');
        Schema::dropIfExists('class_attendance_sessions');
    }
};
