<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('student_transaction_id')->nullable()->constrained('student_transactions')->nullOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('student_enrollment_id')->nullable()->constrained('student_enrollment')->nullOnDelete();
            $table->foreignId('student_tuition_id')->nullable()->constrained('student_tuition')->nullOnDelete();
            $table->string('charge_category', 32)->default('tuition');
            $table->string('target_type', 32)->default('assessment');
            $table->decimal('amount', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['student_tuition_id', 'target_type'], 'payment_allocations_tuition_target_idx');
            $table->index(['student_enrollment_id'], 'payment_allocations_enrollment_idx');
            $table->index(['transaction_id'], 'payment_allocations_transaction_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
