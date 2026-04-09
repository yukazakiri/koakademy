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
        Schema::table('student_clearances', function (Blueprint $table): void {
            // Add index for student_id for faster lookups
            $table->index('student_id', 'idx_student_clearances_student_id');

            // Add composite index for common queries (student_id + academic_year + semester)
            $table->index(['student_id', 'academic_year', 'semester'], 'idx_student_clearances_lookup');

            // Add index for is_cleared status for filtering
            $table->index('is_cleared', 'idx_student_clearances_is_cleared');

            // Add composite index for academic period queries
            $table->index(['academic_year', 'semester'], 'idx_student_clearances_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_clearances', function (Blueprint $table): void {
            // Drop indexes in reverse order
            $table->dropIndex('idx_student_clearances_period');
            $table->dropIndex('idx_student_clearances_is_cleared');
            $table->dropIndex('idx_student_clearances_lookup');
            $table->dropIndex('idx_student_clearances_student_id');
        });
    }
};
