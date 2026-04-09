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
        if (! Schema::hasTable('student_enrollment')) {
            Schema::create('student_enrollment', function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->string('student_id'); // Keep as string for compatibility
                $blueprint->foreignId('course_id')->nullable()->constrained('courses')->onDelete('set null');
                $blueprint->string('status')->default('Pending');
                $blueprint->integer('semester');
                $blueprint->integer('academic_year');
                $blueprint->string('school_year');
                $blueprint->decimal('downpayment', 10, 2)->default(0);
                $blueprint->text('remarks')->nullable();
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
        Schema::dropIfExists('student_enrollment');
    }
};
