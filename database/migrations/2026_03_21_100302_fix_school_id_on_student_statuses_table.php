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
        if (Schema::hasTable('student_statuses')) {
            if (! Schema::hasColumn('student_statuses', 'school_id')) {
                Schema::table('student_statuses', function (Blueprint $table): void {
                    $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
                    $table->index('school_id');
                });
            }

            if (Schema::hasColumn('student_statuses', 'student_id') && Schema::hasTable('students') && Schema::hasColumn('students', 'school_id')) {
                DB::table('student_statuses')
                    ->whereNull('school_id')
                    ->whereExists(function ($query): void {
                        $query->select(DB::raw(1))
                            ->from('students')
                            ->join('schools', 'schools.id', '=', 'students.school_id')
                            ->whereColumn('students.id', 'student_statuses.student_id')
                            ->whereNotNull('students.school_id');
                    })
                    ->update([
                        'school_id' => DB::raw('(SELECT school_id FROM students WHERE students.id = student_statuses.student_id)'),
                    ]);
            }

            if (Schema::hasTable('schools')) {
                $defaultSchoolId = DB::table('schools')->where('code', 'DEFAULT')->value('id')
                    ?? DB::table('schools')->where('is_active', true)->orderBy('id')->value('id')
                    ?? DB::table('schools')->orderBy('id')->value('id');

                if ($defaultSchoolId !== null) {
                    DB::table('student_statuses')->whereNull('school_id')->update(['school_id' => $defaultSchoolId]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('student_statuses') && Schema::hasColumn('student_statuses', 'school_id')) {
            Schema::table('student_statuses', function (Blueprint $table): void {
                $table->dropForeign(['school_id']);
                $table->dropIndex(['school_id']);
                $table->dropColumn('school_id');
            });
        }
    }
};
