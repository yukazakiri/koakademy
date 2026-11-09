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
        $defaultSchoolId = DB::table('schools')->orderBy('id')->value('id');

        // Backfill courses.school_id if NULL
        if ($defaultSchoolId !== null && Schema::hasTable('courses') && Schema::hasColumn('courses', 'school_id')) {
            if (Schema::hasColumn('courses', 'department_id')) {
                $coursesWithDept = DB::table('courses')
                    ->whereNull('school_id')
                    ->whereNotNull('department_id')
                    ->select('id', 'department_id')
                    ->get();

                foreach ($coursesWithDept as $course) {
                    $deptSchoolId = DB::table('departments')->where('id', $course->department_id)->value('school_id');
                    if ($deptSchoolId !== null) {
                        DB::table('courses')->where('id', $course->id)->update(['school_id' => $deptSchoolId]);
                    }
                }
            }

            DB::table('courses')
                ->whereNull('school_id')
                ->update(['school_id' => $defaultSchoolId]);
        }

        // Backfill subject.school_id if NULL
        if ($defaultSchoolId !== null && Schema::hasTable('subject') && Schema::hasColumn('subject', 'school_id')) {
            if (Schema::hasColumn('subject', 'course_id')) {
                $subjectsWithCourse = DB::table('subject')
                    ->whereNull('school_id')
                    ->whereNotNull('course_id')
                    ->select('id', 'course_id')
                    ->get();

                foreach ($subjectsWithCourse as $subject) {
                    $courseSchoolId = DB::table('courses')->where('id', $subject->course_id)->value('school_id');
                    if ($courseSchoolId !== null) {
                        DB::table('subject')->where('id', $subject->id)->update(['school_id' => $courseSchoolId]);
                    }
                }
            }

            DB::table('subject')
                ->whereNull('school_id')
                ->update(['school_id' => $defaultSchoolId]);
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            if (Schema::hasTable('courses')) {
                $coursesIndexes = collect(DB::select("PRAGMA index_list('courses')"))->pluck('name')->all();
                if (in_array('courses_code_unique', $coursesIndexes, true)) {
                    DB::statement('DROP INDEX "courses_code_unique"');
                }
                if (! in_array('courses_school_id_code_unique', $coursesIndexes, true)) {
                    DB::statement('CREATE UNIQUE INDEX "courses_school_id_code_unique" ON "courses" ("school_id", "code")');
                }
            }

            if (Schema::hasTable('subject')) {
                $subjectIndexes = collect(DB::select("PRAGMA index_list('subject')"))->pluck('name')->all();
                if (in_array('subject_code_unique', $subjectIndexes, true)) {
                    DB::statement('DROP INDEX "subject_code_unique"');
                }
                if (! in_array('subject_school_id_code_unique', $subjectIndexes, true)) {
                    DB::statement('CREATE UNIQUE INDEX "subject_school_id_code_unique" ON "subject" ("school_id", "code")');
                }
            }

            return;
        }

        if (Schema::hasTable('courses')) {
            Schema::table('courses', function (Blueprint $table): void {
                if (Schema::hasIndex('courses', 'courses_code_unique')) {
                    $table->dropUnique('courses_code_unique');
                }
                if (! Schema::hasIndex('courses', 'courses_school_id_code_unique')) {
                    $table->unique(['school_id', 'code'], 'courses_school_id_code_unique');
                }
            });
        }

        if (Schema::hasTable('subject')) {
            Schema::table('subject', function (Blueprint $table): void {
                if (Schema::hasIndex('subject', 'subject_code_unique')) {
                    $table->dropUnique('subject_code_unique');
                }
                if (! Schema::hasIndex('subject', 'subject_school_id_code_unique')) {
                    $table->unique(['school_id', 'code'], 'subject_school_id_code_unique');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            if (Schema::hasTable('courses')) {
                $coursesIndexes = collect(DB::select("PRAGMA index_list('courses')"))->pluck('name')->all();
                if (in_array('courses_school_id_code_unique', $coursesIndexes, true)) {
                    DB::statement('DROP INDEX "courses_school_id_code_unique"');
                }
                if (! in_array('courses_code_unique', $coursesIndexes, true)) {
                    DB::statement('CREATE UNIQUE INDEX "courses_code_unique" ON "courses" ("code")');
                }
            }

            if (Schema::hasTable('subject')) {
                $subjectIndexes = collect(DB::select("PRAGMA index_list('subject')"))->pluck('name')->all();
                if (in_array('subject_school_id_code_unique', $subjectIndexes, true)) {
                    DB::statement('DROP INDEX "subject_school_id_code_unique"');
                }
                if (! in_array('subject_code_unique', $subjectIndexes, true)) {
                    DB::statement('CREATE UNIQUE INDEX "subject_code_unique" ON "subject" ("code")');
                }
            }

            return;
        }

        if (Schema::hasTable('courses')) {
            Schema::table('courses', function (Blueprint $table): void {
                if (Schema::hasIndex('courses', 'courses_school_id_code_unique')) {
                    $table->dropUnique('courses_school_id_code_unique');
                }
                if (! Schema::hasIndex('courses', 'courses_code_unique')) {
                    $table->unique('code', 'courses_code_unique');
                }
            });
        }

        if (Schema::hasTable('subject')) {
            Schema::table('subject', function (Blueprint $table): void {
                if (Schema::hasIndex('subject', 'subject_school_id_code_unique')) {
                    $table->dropUnique('subject_school_id_code_unique');
                }
                if (! Schema::hasIndex('subject', 'subject_code_unique')) {
                    $table->unique('code', 'subject_code_unique');
                }
            });
        }
    }
};
