<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('enrollment_class_waitlists')) {
            return;
        }

        Schema::create('enrollment_class_waitlists', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id')->nullable()->index();
            $table->unsignedBigInteger('student_enrollment_id')->index();
            $table->unsignedBigInteger('student_id')->index();
            $table->unsignedBigInteger('subject_enrollment_id')->index();
            $table->unsignedBigInteger('subject_id')->index();
            $table->unsignedBigInteger('preferred_class_id')->nullable()->index();
            $table->string('school_year', 32);
            $table->unsignedTinyInteger('semester');
            $table->unsignedInteger('position')->default(1);
            $table->string('status', 20)->default('waiting'); // waiting | promoted | cancelled
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('added_by')->nullable();
            $table->unsignedBigInteger('promoted_by')->nullable();
            $table->unsignedBigInteger('promoted_class_id')->nullable();
            $table->timestamp('promoted_at')->nullable();
            $table->timestamps();

            $table->index(['school_year', 'semester', 'status'], 'waitlists_term_status_idx');
            $table->index(['subject_id', 'status', 'position'], 'waitlists_subject_queue_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_class_waitlists');
    }
};
