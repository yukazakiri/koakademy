<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        // 1. Ensure admin_transactions.status exists on upgraded installations
        if (Schema::hasTable('admin_transactions') && ! Schema::hasColumn('admin_transactions', 'status')) {
            Schema::table('admin_transactions', function (Blueprint $table): void {
                $table->string('status')->default('pending');
            });
        }

        // 2. Grade range check constraints (0 - 100) on PostgreSQL and MySQL/MariaDB
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE class_enrollments ADD CONSTRAINT class_enrollments_prelim_grade_check CHECK (prelim_grade IS NULL OR (prelim_grade BETWEEN 0 AND 100))');
            DB::statement('ALTER TABLE class_enrollments ADD CONSTRAINT class_enrollments_midterm_grade_check CHECK (midterm_grade IS NULL OR (midterm_grade BETWEEN 0 AND 100))');
            DB::statement('ALTER TABLE class_enrollments ADD CONSTRAINT class_enrollments_finals_grade_check CHECK (finals_grade IS NULL OR (finals_grade BETWEEN 0 AND 100))');
            DB::statement('ALTER TABLE class_enrollments ADD CONSTRAINT class_enrollments_total_average_check CHECK (total_average IS NULL OR (total_average BETWEEN 0 AND 100))');
            DB::statement('ALTER TABLE subject_enrollments ADD CONSTRAINT subject_enrollments_grade_check CHECK (grade IS NULL OR (grade BETWEEN 0 AND 100))');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE class_enrollments ADD CONSTRAINT class_enrollments_prelim_grade_check CHECK (prelim_grade IS NULL OR (prelim_grade BETWEEN 0 AND 100))');
            DB::statement('ALTER TABLE class_enrollments ADD CONSTRAINT class_enrollments_midterm_grade_check CHECK (midterm_grade IS NULL OR (midterm_grade BETWEEN 0 AND 100))');
            DB::statement('ALTER TABLE class_enrollments ADD CONSTRAINT class_enrollments_finals_grade_check CHECK (finals_grade IS NULL OR (finals_grade BETWEEN 0 AND 100))');
            DB::statement('ALTER TABLE class_enrollments ADD CONSTRAINT class_enrollments_total_average_check CHECK (total_average IS NULL OR (total_average BETWEEN 0 AND 100))');
            DB::statement('ALTER TABLE subject_enrollments ADD CONSTRAINT subject_enrollments_grade_check CHECK (grade IS NULL OR (grade BETWEEN 0 AND 100))');
        }

        // 3. Convert JSON columns to jsonb and add GIN indexes on PostgreSQL
        if ($driver === 'pgsql') {
            // PostgreSQL applies USING only to row values, not to existing
            // defaults. Save their expressions and convert them explicitly so
            // legacy text/json defaults do not block the type change.
            $columns = [
                ['classes', 'course_codes'],
                ['classes', 'subject_ids'],
                ['students', 'subject_enrolled'],
                ['students', 'contacts'],
            ];
            $defaults = [];
            $grammar = DB::connection()->getQueryGrammar();
            foreach ($columns as [$table, $column]) {
                $metadata = collect(Schema::getColumns($table))->firstWhere('name', $column);
                if ($metadata === null) {
                    throw new RuntimeException("Cannot convert {$table}.{$column} to jsonb: the column does not exist.");
                }
                $defaults["{$table}.{$column}"] = $metadata['default'];
                if ($metadata['default'] !== null) {
                    DB::statement('ALTER TABLE '.$grammar->wrapTable($table).' ALTER COLUMN '.$grammar->wrap($column).' DROP DEFAULT');
                }
            }

            DB::statement("ALTER TABLE classes ALTER COLUMN course_codes TYPE jsonb USING (
                CASE
                    WHEN course_codes IS NULL OR TRIM(course_codes::text) = '' THEN '[]'::jsonb
                    ELSE course_codes::jsonb
                END
            )");

            DB::statement("ALTER TABLE classes ALTER COLUMN subject_ids TYPE jsonb USING (
                CASE
                    WHEN subject_ids IS NULL OR TRIM(subject_ids::text) = '' THEN '[]'::jsonb
                    ELSE subject_ids::jsonb
                END
            )");

            DB::statement("ALTER TABLE students ALTER COLUMN subject_enrolled TYPE jsonb USING (
                CASE
                    WHEN subject_enrolled IS NULL OR TRIM(subject_enrolled::text) = '' THEN '[]'::jsonb
                    ELSE subject_enrolled::jsonb
                END
            )");

            DB::statement("ALTER TABLE students ALTER COLUMN contacts TYPE jsonb USING (
                CASE
                    WHEN contacts IS NULL OR TRIM(contacts::text) = '' THEN '{}'::jsonb
                    ELSE contacts::jsonb
                END
            )");

            foreach ($columns as [$table, $column]) {
                $default = $defaults["{$table}.{$column}"];
                if ($default !== null) {
                    // PostgreSQL may defer evaluating a default expression until
                    // INSERT. Check it now so bad legacy defaults cannot leave
                    // a successful migration followed by broken new records.
                    DB::select('SELECT (('.$default.')::text)::jsonb');
                    // pg_get_expr supplied this expression from PostgreSQL's
                    // catalog. Cast its value, preserving existing semantics.
                    DB::statement('ALTER TABLE '.$grammar->wrapTable($table).' ALTER COLUMN '.$grammar->wrap($column).' SET DEFAULT (('.$default.')::text)::jsonb');
                }
            }

            DB::statement('CREATE INDEX IF NOT EXISTS classes_course_codes_gin_index ON classes USING GIN (course_codes)');
            DB::statement('CREATE INDEX IF NOT EXISTS classes_subject_ids_gin_index ON classes USING GIN (subject_ids)');
            DB::statement('CREATE INDEX IF NOT EXISTS students_subject_enrolled_gin_index ON students USING GIN (subject_enrolled)');
            DB::statement('CREATE INDEX IF NOT EXISTS students_contacts_gin_index ON students USING GIN (contacts)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS classes_course_codes_gin_index');
            DB::statement('DROP INDEX IF EXISTS classes_subject_ids_gin_index');
            DB::statement('DROP INDEX IF EXISTS students_subject_enrolled_gin_index');
            DB::statement('DROP INDEX IF EXISTS students_contacts_gin_index');

            DB::statement('ALTER TABLE classes ALTER COLUMN course_codes TYPE json USING course_codes::json');
            DB::statement('ALTER TABLE classes ALTER COLUMN subject_ids TYPE json USING subject_ids::json');
            DB::statement('ALTER TABLE students ALTER COLUMN subject_enrolled TYPE json USING subject_enrolled::json');
            DB::statement('ALTER TABLE students ALTER COLUMN contacts TYPE json USING contacts::json');

            DB::statement('ALTER TABLE class_enrollments DROP CONSTRAINT IF EXISTS class_enrollments_prelim_grade_check');
            DB::statement('ALTER TABLE class_enrollments DROP CONSTRAINT IF EXISTS class_enrollments_midterm_grade_check');
            DB::statement('ALTER TABLE class_enrollments DROP CONSTRAINT IF EXISTS class_enrollments_finals_grade_check');
            DB::statement('ALTER TABLE class_enrollments DROP CONSTRAINT IF EXISTS class_enrollments_total_average_check');
            DB::statement('ALTER TABLE subject_enrollments DROP CONSTRAINT IF EXISTS subject_enrollments_grade_check');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE class_enrollments DROP CHECK class_enrollments_prelim_grade_check');
            DB::statement('ALTER TABLE class_enrollments DROP CHECK class_enrollments_midterm_grade_check');
            DB::statement('ALTER TABLE class_enrollments DROP CHECK class_enrollments_finals_grade_check');
            DB::statement('ALTER TABLE class_enrollments DROP CHECK class_enrollments_total_average_check');
            DB::statement('ALTER TABLE subject_enrollments DROP CHECK subject_enrollments_grade_check');
        }

        if (Schema::hasTable('admin_transactions') && Schema::hasColumn('admin_transactions', 'status')) {
            // SQLite refuses to drop a column that an index still references,
            // and a later migration adds an index on this status column.
            foreach (Schema::getIndexes('admin_transactions') as $index) {
                if ($index['primary'] || $index['unique'] || ! in_array('status', $index['columns'], true)) {
                    continue;
                }

                Schema::table('admin_transactions', function (Blueprint $table) use ($index): void {
                    $table->dropIndex($index['name']);
                });
            }

            Schema::table('admin_transactions', function (Blueprint $table): void {
                $table->dropColumn('status');
            });
        }
    }
};
