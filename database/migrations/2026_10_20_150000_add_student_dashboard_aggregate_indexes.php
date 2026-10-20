<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite indexes for the student dashboard aggregates.
 *
 * The dashboard widgets group the `students` table by status, employment
 * status, attrition category, student type and academic year. Without a
 * composite index every grouped COUNT was a full scan of the table.
 *
 * `deleted_at` is part of each index because every query filters soft deleted
 * students out, which keeps the grouping column as the last (and therefore
 * still index usable) part of the key.
 */
return new class extends Migration
{
    /**
     * @var array<string, array<int, string>>
     */
    private const INDEXES = [
        'idx_students_status_employment' => ['status', 'deleted_at', 'employment_status'],
        'idx_students_status_attrition' => ['status', 'deleted_at', 'attrition_category'],
        'idx_students_status_academic_year' => ['status', 'deleted_at', 'academic_year'],
        'idx_students_type_academic_year' => ['student_type', 'deleted_at', 'academic_year'],
        'idx_students_deleted_at_created_at' => ['deleted_at', 'created_at'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('students')) {
            return;
        }

        foreach (self::INDEXES as $name => $columns) {
            if (Schema::hasIndex('students', $name)) {
                continue;
            }

            Schema::table('students', function (Blueprint $table) use ($name, $columns): void {
                $table->index($columns, $name);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('students')) {
            return;
        }

        foreach (array_keys(self::INDEXES) as $name) {
            if (! Schema::hasIndex('students', $name)) {
                continue;
            }

            Schema::table('students', function (Blueprint $table) use ($name): void {
                $table->dropIndex($name);
            });
        }
    }
};
