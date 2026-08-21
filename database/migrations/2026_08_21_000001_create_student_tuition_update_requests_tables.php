<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_tuition_update_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submitted_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('student_enrollment_id')->nullable()->constrained('student_enrollment')->nullOnDelete();
            $table->foreignId('student_tuition_id')->nullable()->constrained('student_tuition')->nullOnDelete();
            $table->string('school_year', 30);
            $table->unsignedTinyInteger('semester');
            $table->string('concern_type', 40);
            $table->string('receipt_number', 255)->nullable();
            $table->text('details');
            $table->string('status', 24)->default('pending');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('tuition_adjustment_id')->nullable()->constrained('tuition_adjustments')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->string('open_key', 128)->nullable()->unique();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'tuition_update_request_status_created_index');
            $table->index(['student_id', 'created_at'], 'tuition_update_request_student_created_index');
            $table->index(['school_year', 'semester'], 'tuition_update_request_period_index');
        });

        Schema::create('student_tuition_update_request_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_tuition_update_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 32);
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['student_tuition_update_request_id', 'created_at'], 'tuition_update_request_event_timeline_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_tuition_update_request_events');
        Schema::dropIfExists('student_tuition_update_requests');
    }
};
