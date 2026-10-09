<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * student_enrollment.student_id is varchar in production (legacy data)
     * while students.id is bigint, so relationship joins such as
     * whereHas('student') throw SQLSTATE[42883].
     *
     * Intentionally no FOREIGN KEY: 2 legacy rows reference
     * non-existent students and must keep working.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("UPDATE student_enrollment SET student_id = '0' WHERE TRIM(student_id) = ''");
        DB::statement('ALTER TABLE student_enrollment ALTER COLUMN student_id TYPE BIGINT USING student_id::bigint');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE student_enrollment ALTER COLUMN student_id TYPE VARCHAR USING student_id::text');
    }
};