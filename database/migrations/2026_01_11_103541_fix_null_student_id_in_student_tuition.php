<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Fix 175 student_tuition records that have null student_id
     * by populating them from their related enrollment's student_id.
     *
     * Note: student_tuition.student_id is bigint, but student_enrollment.student_id is varchar
     * so we need to cast the varchar to bigint.
     */
    public function up(): void
    {
        // Update student_tuition records where student_id is null
        // by joining with student_enrollment to get the student_id
        // Cast varchar to INTEGER since the column types differ
        // Note: SQLite doesn't support UPDATE...FROM, so we use a subquery
        $driver = DB::getDriverName();
        $regexOp = match ($driver) {
            'pgsql' => "~ '^[0-9]+$'",
            'sqlite' => "GLOB '^[0-9][0-9]*$'",
            default => "REGEXP '^[0-9]+$'"
        };

        DB::statement("
            UPDATE student_tuition
            SET student_id = CAST(
                (SELECT student_enrollment.student_id
                 FROM student_enrollment
                 WHERE student_tuition.enrollment_id = student_enrollment.id
                 AND student_enrollment.student_id IS NOT NULL
                 AND student_enrollment.student_id {$regexOp}
                 LIMIT 1)
                AS INTEGER
            )
            WHERE student_id IS NULL
            AND EXISTS (
                SELECT 1
                FROM student_enrollment
                WHERE student_tuition.enrollment_id = student_enrollment.id
                AND student_enrollment.student_id IS NOT NULL
                AND student_enrollment.student_id {$regexOp}
            )
        ");
    }

    /**
     * Reverse the migrations.
     *
     * Note: We cannot reliably reverse this migration as we don't know
     * which records originally had null student_id values.
     */
    public function down(): void
    {
        // Cannot be reversed - the original null values are not tracked
    }
};
