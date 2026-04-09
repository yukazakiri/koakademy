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
        $tables = [
            'students',
            'class_enrollments',
            'student_enrollment',
            'faculty',
            'shs_students',
            'subject_enrollments',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'school_id')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
                    $table->index('school_id');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'students',
            'class_enrollments',
            'student_enrollment',
            'faculty',
            'shs_students',
            'subject_enrollments',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasColumn($tableName, 'school_id')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropForeign(['school_id']);
                    $table->dropIndex(['school_id']);
                    $table->dropColumn('school_id');
                });
            }
        }
    }
};
