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
        if (! Schema::hasTable('strand_subjects')) {
            Schema::create('strand_subjects', function (Blueprint $table): void {
                $table->id();
                $table->string('code')->unique();
                $table->string('title');
                $table->text('description')->nullable();
                $table->integer('grade_year')->default(11);
                $table->integer('semester')->default(1);
                $table->foreignId('strand_id')->nullable()->constrained('shs_strands')->onDelete('set null');
                $table->timestamps();

                // Index for better performance
                $table->index(['strand_id', 'grade_year', 'semester']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('strand_subjects');
    }
};
