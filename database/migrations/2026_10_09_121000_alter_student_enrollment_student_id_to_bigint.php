<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("UPDATE student_enrollment SET student_id = '0' WHERE TRIM(student_id) = ''");
            DB::statement('ALTER TABLE student_enrollment ALTER COLUMN student_id TYPE BIGINT USING student_id::bigint');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("UPDATE student_enrollment SET student_id = '0' WHERE TRIM(student_id) = ''");
            DB::statement('ALTER TABLE student_enrollment MODIFY student_id BIGINT UNSIGNED NULL');
        } else {
            DB::statement("UPDATE student_enrollment SET student_id = '0' WHERE TRIM(student_id) = ''");
            Schema::table('student_enrollment', function (Blueprint $table): void {
                $table->unsignedBigInteger('student_id')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE student_enrollment ALTER COLUMN student_id TYPE VARCHAR USING student_id::text');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE student_enrollment MODIFY student_id VARCHAR(255) NULL');
        } else {
            Schema::table('student_enrollment', function (Blueprint $table): void {
                $table->string('student_id')->nullable()->change();
            });
        }
    }
};
