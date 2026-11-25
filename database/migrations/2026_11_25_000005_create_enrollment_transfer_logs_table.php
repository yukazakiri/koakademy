<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('enrollment_transfer_logs')) {
            return;
        }

        Schema::create('enrollment_transfer_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id')->nullable()->index();
            $table->unsignedBigInteger('student_enrollment_id');
            $table->unsignedBigInteger('student_id')->index();
            $table->string('transfer_type', 30); // section_transfer | program_shift
            $table->unsignedBigInteger('from_course_id')->nullable();
            $table->unsignedBigInteger('to_course_id')->nullable();
            $table->string('from_section', 80)->nullable();
            $table->string('to_section', 80)->nullable();
            $table->unsignedInteger('credited_subjects_count')->default(0);
            $table->unsignedInteger('reassigned_classes_count')->default(0);
            $table->unsignedInteger('unmatched_subjects_count')->default(0);
            $table->decimal('previous_tuition_total', 10, 2)->default(0);
            $table->decimal('new_tuition_total', 10, 2)->default(0);
            $table->decimal('fee_delta', 10, 2)->default(0);
            $table->json('details')->nullable();
            $table->text('reason');
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->timestamps();

            $table->index(['student_enrollment_id', 'transfer_type'], 'enrollment_transfers_enr_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_transfer_logs');
    }
};
