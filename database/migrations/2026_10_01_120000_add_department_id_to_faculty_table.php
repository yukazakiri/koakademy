<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a real foreign key from faculty to departments.
 *
 * Faculty.department is a free-text string matched by code OR name
 * (Department::faculty()). That is slow, misses rows where the string drifted from the
 * department code, and cannot express "unassigned". This adds a nullable FK and backfills it.
 *
 * Unlike the equivalent change on courses, the string column is KEPT: it is still written and
 * read by the Filament faculty resource, the MCP faculty tools, DigitalIdCardService and
 * FacultySearchable::toSearchableArray(). Dropping it would break all of them. New code should
 * read department_id.
 *
 * Note the primary key on faculty is a uuid, so the backfill keys off id and the FK column is a
 * plain unsignedBigInteger referencing departments.id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('faculty', 'department_id')) {
            return;
        }

        Schema::table('faculty', function (Blueprint $table): void {
            $table->unsignedBigInteger('department_id')->nullable()->after('department');
        });

        $this->backfill();

        Schema::table('faculty', function (Blueprint $table): void {
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
            $table->index(['department_id', 'school_id'], 'faculty_department_school_index');
            $table->index(['school_id', 'department_id'], 'faculty_school_department_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('faculty', 'department_id')) {
            return;
        }

        Schema::table('faculty', function (Blueprint $table): void {
            $table->dropForeign(['department_id']);
            $table->dropIndex('faculty_department_school_index');
            $table->dropIndex('faculty_school_department_index');
            $table->dropColumn('department_id');
        });
    }

    /**
     * Resolve each faculty row to a department, preferring an exact code match over a name match.
     *
     * Done in one grouped pass per school rather than a query per faculty row, so this stays
     * fast on a large faculty table.
     */
    private function backfill(): void
    {
        $departments = DB::table('departments')->get(['id', 'school_id', 'code', 'name']);

        if ($departments->isEmpty()) {
            return;
        }

        // Per school: uppercase code -> id, then uppercase name -> id as the fallback.
        $byCode = [];
        $byName = [];

        foreach ($departments as $department) {
            $schoolId = (int) $department->school_id;

            $code = mb_strtoupper(mb_trim((string) $department->code));
            $name = mb_strtoupper(mb_trim((string) $department->name));

            if ($code !== '') {
                $byCode[$schoolId][$code] = (int) $department->id;
            }

            if ($name !== '') {
                $byName[$schoolId][$name] ??= (int) $department->id;
            }
        }

        DB::table('faculty')
            ->select(['id', 'school_id', 'department'])
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->orderBy('id')
            ->chunk(500, function ($facultyRows) use ($byCode, $byName): void {
                foreach ($facultyRows as $faculty) {
                    $departmentId = $this->resolveDepartment($faculty, $byCode, $byName);

                    if ($departmentId !== null) {
                        DB::table('faculty')
                            ->where('id', $faculty->id)
                            ->update(['department_id' => $departmentId]);
                    }
                }
            });
    }

    /**
     * @param  array<int, array<string, int>>  $byCode
     * @param  array<int, array<string, int>>  $byName
     */
    private function resolveDepartment(object $faculty, array $byCode, array $byName): ?int
    {
        $raw = mb_trim((string) $faculty->department);

        if ($raw === '') {
            return null;
        }

        $schoolId = (int) ($faculty->school_id ?? 0);
        $value = mb_strtoupper($raw);

        // A code match is authoritative; the name map is the compatibility fallback for rows
        // that were entered with the department's full name instead of its code.
        return $byCode[$schoolId][$value]
            ?? $byName[$schoolId][$value]
            ?? null;
    }
};
