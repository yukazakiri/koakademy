<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_statuses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('academic_year');
            $table->unsignedTinyInteger('semester');
            $table->string('status');
            $table->timestamps();

            $table->unique(['student_id', 'academic_year', 'semester'], 'student_statuses_unique');
            $table->index(['academic_year', 'semester', 'status'], 'student_statuses_period_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_statuses');
    }
};
