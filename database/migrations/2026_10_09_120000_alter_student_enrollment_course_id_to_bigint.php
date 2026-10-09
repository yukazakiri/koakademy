<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Align prod with the schema: student_enrollment.course_id is varchar
     * in production (legacy data) while courses.id is bigint, so plain
     * joins throw SQLSTATE[42883]. Normalize blank values then convert.
     *
     * Intentionally no FOREIGN KEY: 148 legacy rows reference
     * non-existent courses and must keep working (inner joins skip them).
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("UPDATE student_enrollment SET course_id = '0' WHERE TRIM(course_id) = ''");
        DB::statement('ALTER TABLE student_enrollment ALTER COLUMN course_id TYPE BIGINT USING course_id::bigint');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE student_enrollment ALTER COLUMN course_id TYPE VARCHAR USING course_id::text');
    }
};
