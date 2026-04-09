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
        if (! Schema::hasTable('subject_enrollments')) {
            Schema::create('subject_enrollments', function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->foreignId('subject_id')->constrained('subject')->onDelete('cascade');
                $blueprint->foreignId('class_id')->nullable()->constrained('classes')->onDelete('set null');
                $blueprint->decimal('grade', 5, 2)->nullable();
                $blueprint->string('instructor')->nullable();
                $blueprint->integer('student_id');
                $blueprint->integer('academic_year')->nullable();
                $blueprint->string('school_year');
                $blueprint->integer('semester');
                $blueprint->foreignId('enrollment_id')->constrained('student_enrollment')->onDelete('cascade');
                $blueprint->text('remarks')->nullable();
                $blueprint->string('classification')->nullable();
                $blueprint->string('school_name')->nullable();
                $blueprint->boolean('is_credited')->default(false);
                $blueprint->integer('credited_subject_id')->nullable();
                $blueprint->string('section')->nullable();
                $blueprint->boolean('is_modular')->default(false);
                $blueprint->decimal('lecture_fee', 10, 2)->default(0);
                $blueprint->decimal('laboratory_fee', 10, 2)->default(0);
                $blueprint->integer('enrolled_lecture_units')->default(0);
                $blueprint->integer('enrolled_laboratory_units')->default(0);
                $blueprint->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subject_enrollments');
    }
};
