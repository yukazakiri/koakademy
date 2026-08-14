<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tuition_adjustment_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 24)->default('workspace');
            $table->string('status', 24)->default('processing');
            $table->unsignedInteger('recorded_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->timestamps();
        });

        Schema::create('tuition_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('tuition_adjustment_batches')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('student_enrollment_id')->nullable()->constrained('student_enrollment')->nullOnDelete();
            $table->foreignId('student_tuition_id')->nullable()->constrained('student_tuition')->nullOnDelete();
            $table->string('client_row_id', 100);
            $table->string('idempotency_key', 160)->unique();
            $table->string('source', 24)->default('workspace');
            $table->text('reason');
            $table->json('before_snapshot');
            $table->json('after_snapshot');
            $table->json('configuration_snapshot');
            $table->json('delivery_status')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'client_row_id'], 'tuition_adjustment_batch_row_unique');
            $table->index(['student_enrollment_id', 'created_at'], 'tuition_adjustment_enrollment_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tuition_adjustments');
        Schema::dropIfExists('tuition_adjustment_batches');
    }
};
