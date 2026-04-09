<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_research_papers', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->enum('type', ['capstone', 'thesis', 'research']);
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->string('advisor_name')->nullable();
            $table->string('contributors')->nullable();
            $table->text('abstract')->nullable();
            $table->text('keywords')->nullable();
            $table->integer('publication_year')->nullable();
            $table->string('document_url')->nullable();
            $table->enum('status', ['draft', 'submitted', 'archived'])->default('draft');
            $table->boolean('is_public')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['title']);
            $table->index(['type', 'status']);
            $table->index(['publication_year']);
            $table->index(['is_public']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_research_papers');
    }
};
