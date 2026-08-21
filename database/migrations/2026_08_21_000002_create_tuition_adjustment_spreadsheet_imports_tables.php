<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tuition_adjustment_spreadsheet_imports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('uploaded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('checksum', 64);
            $table->string('school_year', 30);
            $table->unsignedTinyInteger('semester');
            $table->string('status', 24)->default('review');
            $table->unsignedInteger('ready_count')->default(0);
            $table->unsignedInteger('invalid_count')->default(0);
            $table->unsignedInteger('applied_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['school_year', 'semester']);
        });

        Schema::create('tuition_adjustment_spreadsheet_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tuition_adjustment_spreadsheet_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('student_number', 100)->nullable();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('student_enrollment_id')->nullable()->constrained('student_enrollment')->nullOnDelete();
            $table->foreignId('student_tuition_id')->nullable()->constrained('student_tuition')->nullOnDelete();
            $table->foreignId('tuition_adjustment_id')->nullable()->constrained('tuition_adjustments')->nullOnDelete();
            $table->json('input');
            $table->json('canonical_snapshot')->nullable();
            $table->json('proposal')->nullable();
            $table->json('errors')->nullable();
            $table->json('result')->nullable();
            $table->string('status', 24)->default('invalid');
            $table->timestamps();

            $table->unique(['tuition_adjustment_spreadsheet_import_id', 'row_number'], 'tuition_adjustment_spreadsheet_import_row_unique');
            $table->index(['tuition_adjustment_spreadsheet_import_id', 'status'], 'tuition_adjustment_spreadsheet_import_state_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tuition_adjustment_spreadsheet_import_rows');
        Schema::dropIfExists('tuition_adjustment_spreadsheet_imports');
    }
};
