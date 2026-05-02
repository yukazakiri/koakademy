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
        Schema::create('class_post_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_post_id')
                ->constrained('class_posts')
                ->cascadeOnDelete();
            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();
            $table->text('content')->nullable();
            $table->json('attachments')->nullable();
            $table->unsignedInteger('points')->nullable();
            $table->text('feedback')->nullable();
            $table->string('status')->default('submitted'); // submitted, graded, returned
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->unique(['class_post_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_post_submissions');
    }
};
