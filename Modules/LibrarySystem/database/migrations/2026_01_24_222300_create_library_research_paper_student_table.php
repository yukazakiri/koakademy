<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_research_paper_student', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('research_paper_id')
                ->constrained('library_research_papers')
                ->cascadeOnDelete();
            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['research_paper_id', 'student_id']);
            $table->index(['student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_research_paper_student');
    }
};
