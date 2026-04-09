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
        if (! Schema::hasTable('class_enrollments')) {
            Schema::create('class_enrollments', function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->foreignId('class_id')->constrained('classes')->onDelete('cascade');
                $blueprint->decimal('student_id', 15, 0); // Keep as decimal for compatibility
                $blueprint->datetime('completion_date')->nullable();
                $blueprint->boolean('status')->default(true);
                $blueprint->text('remarks')->nullable();
                $blueprint->decimal('prelim_grade', 5, 2)->nullable();
                $blueprint->decimal('midterm_grade', 5, 2)->nullable();
                $blueprint->decimal('finals_grade', 5, 2)->nullable();
                $blueprint->decimal('total_average', 5, 2)->nullable();
                $blueprint->boolean('is_grades_finalized')->default(false);
                $blueprint->boolean('is_grades_verified')->default(false);
                $blueprint->integer('verified_by')->nullable();
                $blueprint->datetime('verified_at')->nullable();
                $blueprint->text('verification_notes')->nullable();
                $blueprint->timestamps();
                $blueprint->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_enrollments');
    }
};
