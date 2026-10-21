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

        // A fresh install creates the column as a bigint through foreignId(),
        // so there is nothing to convert. Production still holds the legacy
        // varchar, and running TRIM() against a bigint fails with
        // "function pg_catalog.btrim(bigint) does not exist", which stopped a
        // fresh PostgreSQL install from migrating at all.
        if (! $this->courseIdIsText()) {
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

        // Only reverse the conversion this migration performed. On a fresh
        // install the column was already a bigint and still carries the
        // foreign key to courses, so turning it back into text would undo the
        // schema the install started from.
        if (! $this->courseIdIsBigint() || $this->courseIdHasForeignKey()) {
            return;
        }

        DB::statement('ALTER TABLE student_enrollment ALTER COLUMN course_id TYPE VARCHAR USING course_id::text');
    }

    /**
     * Whether the course_id column still holds the legacy text values.
     */
    private function courseIdIsText(): bool
    {
        return in_array($this->courseIdType(), ['character varying', 'text', 'character'], true);
    }

    /**
     * Whether the course_id column is already a bigint.
     */
    private function courseIdIsBigint(): bool
    {
        return $this->courseIdType() === 'bigint';
    }

    /**
     * The PostgreSQL type of student_enrollment.course_id.
     */
    private function courseIdType(): ?string
    {
        $column = DB::selectOne(
            "SELECT data_type FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'student_enrollment' AND column_name = 'course_id'"
        );

        return $column?->data_type;
    }

    /**
     * Whether the course_id column still carries a foreign key to courses.
     */
    private function courseIdHasForeignKey(): bool
    {
        $constraint = DB::selectOne(
            "SELECT tc.constraint_name
             FROM information_schema.table_constraints AS tc
             JOIN information_schema.key_column_usage AS kcu
               ON kcu.constraint_name = tc.constraint_name
              AND kcu.constraint_schema = tc.constraint_schema
             WHERE tc.constraint_type = 'FOREIGN KEY'
               AND tc.table_name = 'student_enrollment'
               AND kcu.column_name = 'course_id'
             LIMIT 1"
        );

        return $constraint !== null;
    }
};
