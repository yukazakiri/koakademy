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
     *
     * Normalizes student_id across all tables to unsigned bigint,
     * adds performance indexes, purges legacy orphan enrollment rows,
     * and adds the foreign key constraint on student_enrollment.student_id.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        // 1. Normalise data & column types
        if ($driver === 'pgsql') {
            DB::statement("UPDATE student_enrollment SET student_id = NULL WHERE student_id IS NOT NULL AND (TRIM(CAST(student_id AS TEXT)) = '' OR TRIM(CAST(student_id AS TEXT)) = '0')");
            DB::statement('ALTER TABLE student_enrollment ALTER COLUMN student_id TYPE BIGINT USING (student_id::bigint)');
            DB::statement('ALTER TABLE class_enrollments ALTER COLUMN student_id TYPE BIGINT USING (student_id::bigint)');
            DB::statement('ALTER TABLE subject_enrollments ALTER COLUMN student_id TYPE BIGINT USING (student_id::bigint)');
            DB::statement('ALTER TABLE student_tuition ALTER COLUMN student_id TYPE BIGINT USING (student_id::bigint)');
            DB::statement('ALTER TABLE student_transactions ALTER COLUMN student_id TYPE BIGINT USING (student_id::bigint)');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("UPDATE student_enrollment SET student_id = NULL WHERE student_id IS NOT NULL AND (TRIM(CAST(student_id AS CHAR)) = '' OR TRIM(CAST(student_id AS CHAR)) = '0')");
            DB::statement('ALTER TABLE student_enrollment MODIFY student_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE student_enrollment MODIFY course_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE class_enrollments MODIFY student_id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE subject_enrollments MODIFY student_id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE student_tuition MODIFY student_id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE student_transactions MODIFY student_id BIGINT UNSIGNED NOT NULL');
        } else {
            // SQLite and other drivers
            DB::statement("UPDATE student_enrollment SET student_id = NULL WHERE student_id IS NOT NULL AND (TRIM(CAST(student_id AS TEXT)) = '' OR TRIM(CAST(student_id AS TEXT)) = '0')");
            Schema::table('student_enrollment', function (Blueprint $table): void {
                $table->unsignedBigInteger('student_id')->nullable()->change();
                $table->unsignedBigInteger('course_id')->nullable()->change();
            });
            Schema::table('class_enrollments', function (Blueprint $table): void {
                $table->unsignedBigInteger('student_id')->change();
            });
            Schema::table('subject_enrollments', function (Blueprint $table): void {
                $table->unsignedBigInteger('student_id')->change();
            });
            Schema::table('student_tuition', function (Blueprint $table): void {
                $table->unsignedBigInteger('student_id')->change();
            });
            Schema::table('student_transactions', function (Blueprint $table): void {
                $table->unsignedBigInteger('student_id')->change();
            });
        }

        // 2. Add performance indexes
        $indexes = [
            'student_enrollment' => [
                'student_id' => 'student_enrollment_student_id_index',
                'course_id' => 'student_enrollment_course_id_index',
            ],
            'class_enrollments' => [
                'student_id' => 'class_enrollments_student_id_index',
            ],
            'subject_enrollments' => [
                'student_id' => 'subject_enrollments_student_id_index',
            ],
            'student_tuition' => [
                'student_id' => 'student_tuition_student_id_index',
            ],
            'student_transactions' => [
                'student_id' => 'student_transactions_student_id_index',
            ],
        ];

        foreach ($indexes as $table => $columnIndexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columnIndexes as $column => $indexName) {
                if (! Schema::hasIndex($table, $indexName)) {
                    Schema::table($table, function (Blueprint $tableBlueprint) use ($column, $indexName): void {
                        $tableBlueprint->index($column, $indexName);
                    });
                }
            }
        }

        // 3. Purge legacy orphan student_enrollment rows before creating foreign key
        if (Schema::hasTable('student_enrollment') && Schema::hasTable('students')) {
            DB::table('student_enrollment')
                ->whereNotNull('student_id')
                ->whereNotIn('student_id', DB::table('students')->select('id'))
                ->delete();

            if (! $this->hasStudentForeignKey()) {
                Schema::table('student_enrollment', function (Blueprint $table): void {
                    $table->foreign('student_id', 'student_enrollment_student_id_foreign')
                        ->references('id')
                        ->on('students')
                        ->onDelete('cascade');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('student_enrollment') && Schema::hasForeignKey('student_enrollment', 'student_enrollment_student_id_foreign')) {
            Schema::table('student_enrollment', function (Blueprint $table): void {
                $table->dropForeign('student_enrollment_student_id_foreign');
            });
        }

        $indexes = [
            'student_enrollment' => [
                'student_enrollment_student_id_index',
                'student_enrollment_course_id_index',
            ],
            'class_enrollments' => [
                'class_enrollments_student_id_index',
            ],
            'subject_enrollments' => [
                'subject_enrollments_student_id_index',
            ],
            'student_tuition' => [
                'student_tuition_student_id_index',
            ],
            'student_transactions' => [
                'student_transactions_student_id_index',
            ],
        ];

        foreach ($indexes as $table => $tableIndexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($tableIndexes as $indexName) {
                if (Schema::hasIndex($table, $indexName)) {
                    Schema::table($table, function (Blueprint $tableBlueprint) use ($indexName): void {
                        $tableBlueprint->dropIndex($indexName);
                    });
                }
            }
        }
    }

    /**
     * Whether student_enrollment already has a foreign key to students.
     */
    private function hasStudentForeignKey(): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            $fks = DB::select('PRAGMA foreign_key_list(student_enrollment)');
            foreach ($fks as $fk) {
                if (($fk->from ?? '') === 'student_id' && ($fk->table ?? '') === 'students') {
                    return true;
                }
            }

            return false;
        }

        return Schema::hasForeignKey('student_enrollment', 'student_enrollment_student_id_foreign');
    }
};
