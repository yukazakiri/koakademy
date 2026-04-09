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
        if (! Schema::hasTable('student_tuition')) {
            Schema::create('student_tuition', function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->decimal('total_tuition', 10, 2)->default(0);
                $blueprint->decimal('total_balance', 10, 2)->default(0);
                $blueprint->decimal('total_lectures', 10, 2)->default(0);
                $blueprint->decimal('total_laboratory', 10, 2)->default(0);
                $blueprint->decimal('total_miscelaneous_fees', 10, 2)->default(0);
                $blueprint->string('status')->default('pending');
                $blueprint->integer('semester');
                $blueprint->string('school_year');
                $blueprint->integer('academic_year');
                $blueprint->integer('student_id');
                $blueprint->foreignId('enrollment_id')->nullable()->constrained('student_enrollment')->onDelete('cascade');
                $blueprint->integer('discount')->default(0);
                $blueprint->decimal('downpayment', 10, 2)->default(0);
                $blueprint->decimal('overall_tuition', 10, 2)->default(0);
                $blueprint->integer('paid')->default(0);
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
        Schema::dropIfExists('student_tuition');
    }
};
