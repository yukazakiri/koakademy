<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_revisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('student_tuition_id')->constrained('student_tuition')->cascadeOnDelete();
            $table->foreignId('student_enrollment_id')->constrained('student_enrollment')->cascadeOnDelete();
            $table->foreignId('fee_schedule_version_id')->nullable()->constrained('fee_schedule_versions')->nullOnDelete();
            $table->foreignId('tuition_adjustment_id')->nullable()->constrained('tuition_adjustments')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('predecessor_id')->nullable()->constrained('assessment_revisions')->nullOnDelete();
            $table->unsignedInteger('revision_number')->default(1);
            $table->string('calculation_method', 32)->default('reconciled_assessment');
            $table->string('status', 24)->default('approved');
            $table->string('source', 32)->default('workspace');
            $table->text('reason')->nullable();

            $table->decimal('gross_lecture', 12, 2)->default(0);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('discounted_lecture', 12, 2)->default(0);
            $table->decimal('laboratory', 12, 2)->default(0);
            $table->decimal('modular', 12, 2)->default(0);
            $table->decimal('total_tuition', 12, 2)->default(0);
            $table->decimal('miscellaneous', 12, 2)->default(0);
            $table->decimal('additional_fees', 12, 2)->default(0);
            $table->decimal('assessment_adjustment', 12, 2)->default(0);
            $table->decimal('overall_tuition', 12, 2)->default(0);
            $table->decimal('required_downpayment', 12, 2)->default(0);
            $table->decimal('opening_paid', 12, 2)->default(0);
            $table->decimal('verified_paid', 12, 2)->default(0);
            $table->decimal('total_paid', 12, 2)->default(0);
            $table->decimal('balance_due', 12, 2)->default(0);
            $table->decimal('credit', 12, 2)->default(0);

            $table->json('installments')->nullable();
            $table->json('calculation_inputs')->nullable();
            $table->string('fingerprint', 64);
            $table->foreignId('pdf_resource_id')->nullable()->constrained('resources')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_tuition_id', 'revision_number'], 'assessment_revisions_tuition_rev_unique');
            $table->index(['student_enrollment_id', 'status'], 'assessment_revisions_enrollment_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_revisions');
    }
};
