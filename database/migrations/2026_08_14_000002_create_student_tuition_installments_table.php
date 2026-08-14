<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_tuition_installments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_tuition_id')->constrained('student_tuition')->cascadeOnDelete();
            $table->string('term', 32);
            $table->unsignedTinyInteger('sequence');
            $table->decimal('percentage', 5, 2);
            $table->decimal('amount', 12, 2);
            $table->string('source', 20)->default('generated');
            $table->timestamps();

            $table->unique(['student_tuition_id', 'term'], 'tuition_installment_term_unique');
            $table->index(['student_tuition_id', 'sequence'], 'tuition_installment_sequence_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_tuition_installments');
    }
};
