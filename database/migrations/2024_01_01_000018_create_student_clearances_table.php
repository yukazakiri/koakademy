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
        if (! Schema::hasTable('student_clearances')) {
            Schema::create('student_clearances', function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->foreignId('student_id')->constrained('students')->onDelete('cascade');
                $blueprint->string('academic_year');
                $blueprint->integer('semester');
                $blueprint->boolean('is_cleared')->default(false);
                $blueprint->string('cleared_by')->nullable();
                $blueprint->timestamp('cleared_at')->nullable();
                $blueprint->text('remarks')->nullable();
                $blueprint->timestamps();

                $blueprint->unique(['student_id', 'academic_year', 'semester']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_clearances');
    }
};
