<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('enrollment_load_changes')) {
            return;
        }

        Schema::create('enrollment_load_changes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id')->nullable()->index();
            $table->string('reference_number', 40)->unique();
            $table->unsignedBigInteger('student_enrollment_id');
            $table->unsignedBigInteger('student_id')->index();
            $table->string('change_type', 20); // add | drop | swap
            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->unsignedBigInteger('added_subject_id')->nullable();
            $table->unsignedBigInteger('added_class_id')->nullable();
            $table->string('added_subject_code', 60)->nullable();
            $table->string('added_subject_title', 200)->nullable();
            $table->unsignedBigInteger('dropped_subject_enrollment_id')->nullable();
            $table->unsignedBigInteger('dropped_subject_id')->nullable();
            $table->string('dropped_subject_code', 60)->nullable();
            $table->string('dropped_subject_title', 200)->nullable();
            $table->integer('units_delta')->default(0);
            $table->decimal('tuition_delta', 10, 2)->default(0);
            $table->decimal('previous_tuition_total', 10, 2)->default(0);
            $table->decimal('new_tuition_total', 10, 2)->default(0);
            $table->boolean('within_window')->default(true);
            $table->text('reason');
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['student_enrollment_id', 'status'], 'enrollment_load_changes_enr_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_load_changes');
    }
};
