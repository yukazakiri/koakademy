<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_exports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('stage')->default('queued');
            $table->json('filters');
            $table->string('batch_id')->nullable()->index();
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('completed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('merged_parts')->default(0);
            $table->unsignedInteger('total_parts')->default(0);
            $table->unsignedTinyInteger('percentage')->default(0);
            $table->text('message')->nullable();
            $table->string('output_disk')->nullable();
            $table->text('output_path')->nullable();
            $table->string('output_name')->nullable();
            $table->text('report_path')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->json('error_context')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('merge_dispatched_at')->nullable();
            $table->timestamp('cancel_requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('terminal_notified_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'user_id', 'status', 'created_at'], 'assessment_exports_school_user_status');
            $table->index(['status', 'updated_at'], 'assessment_exports_status_updated');
        });

        Schema::create('assessment_export_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('assessment_export_id');
            $table->foreign('assessment_export_id')->references('id')->on('assessment_exports')->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained('student_enrollment')->nullOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('status')->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('artifact_disk')->nullable();
            $table->text('artifact_path')->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->unsignedBigInteger('byte_size')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->json('error_context')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['assessment_export_id', 'sequence'], 'assessment_export_items_sequence_unique');
            $table->unique(['assessment_export_id', 'enrollment_id'], 'assessment_export_items_enrollment_unique');
            $table->index(['assessment_export_id', 'status'], 'assessment_export_items_export_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_export_items');
        Schema::dropIfExists('assessment_exports');
    }
};
