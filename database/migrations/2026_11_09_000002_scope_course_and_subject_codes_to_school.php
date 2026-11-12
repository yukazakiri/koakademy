<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scopes the program code and the subject code to the school that owns them.
 *
 * A program code identifies one curriculum implementation inside one school,
 * and a subject code identifies one subject inside one program. Two keys
 * express that:
 *
 *   - `courses_school_id_code_unique` on (school_id, code)
 *   - `subject_school_id_course_id_code_unique` on (school_id, course_id, code)
 *
 * The subject key includes the course because a school runs several
 * programs, and each program repeats the generic codes of its curriculum:
 * `NSTP 1`, `PE 2` and `GE-1` exist once per program. A school wide key on
 * the subject code would reject every program that follows the second one.
 *
 * The earlier revision of this migration keyed the subject code on
 * (school_id, code) alone, which no curriculum can satisfy, and it created
 * the program key with a plain `unique()` inside the migration transaction.
 * On a database that already held the same program code twice it failed with
 *
 *   ERROR: could not create unique index "courses_school_id_code_unique"
 *   DETAIL: Key (school_id, code)=(4, BSHRM (2009 - 2010)) is duplicated.
 *
 * This revision is written to survive that situation:
 *
 *   - Duplicate program codes are merged by
 *     `2026_11_08_000001_merge_duplicate_courses` before this migration
 *     runs. Any duplicate that survives is reported with the offending keys
 *     instead of a raw database error.
 *   - Every key is checked before it is created, so a rerun is a no-op.
 *   - On PostgreSQL the keys are built with CREATE UNIQUE INDEX CONCURRENTLY
 *     so a deployment does not lock the curriculum tables while the key is
 *     built. PostgreSQL refuses to run that inside a transaction block, so
 *     this migration opts out of the transaction wrapper.
 *   - A key left invalid by an interrupted concurrent build is removed
 *     before the retry, which makes the migration resumable.
 */
return new class extends Migration
{
    /**
     * The program key: one program code per school.
     */
    private const COURSE_UNIQUE_INDEX = 'courses_school_id_code_unique';

    /**
     * The subject key: one subject code per program.
     */
    private const SUBJECT_UNIQUE_INDEX = 'subject_school_id_course_id_code_unique';

    /**
     * The global program key this migration replaces.
     */
    private const LEGACY_COURSE_INDEX = 'courses_code_unique';

    /**
     * The global subject key this migration replaces.
     */
    private const LEGACY_SUBJECT_INDEX = 'subject_code_unique';

    /**
     * The school wide subject key an earlier revision of this migration
     * created. It is superseded by the per program key and has to go, but
     * only after the replacement exists.
     */
    private const SUPERSEDED_SUBJECT_INDEX = 'subject_school_id_code_unique';

    /**
     * Opt out of the transaction wrapper: PostgreSQL refuses to run
     * CREATE INDEX CONCURRENTLY inside a transaction block.
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->backfillSchools();

        if (! Schema::hasTable('courses') || ! Schema::hasTable('subject')) {
            return;
        }

        $this->assertNoDuplicateCourseCodes();
        $this->assertNoDuplicateSubjectCodes();

        // The replacement keys are built first, so the curriculum tables are
        // never left without a unique key, not even for a moment.
        $this->createUniqueIndex('courses', self::COURSE_UNIQUE_INDEX, ['school_id', 'code']);
        $this->createUniqueIndex('subject', self::SUBJECT_UNIQUE_INDEX, ['school_id', 'course_id', 'code']);

        $this->dropUniqueIndex('courses', self::LEGACY_COURSE_INDEX);
        $this->dropUniqueIndex('subject', self::LEGACY_SUBJECT_INDEX);
        $this->dropUniqueIndex('subject', self::SUPERSEDED_SUBJECT_INDEX);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('courses')) {
            return;
        }

        $this->dropUniqueIndex('courses', self::COURSE_UNIQUE_INDEX);

        if (! Schema::hasTable('subject')) {
            return;
        }

        $this->dropUniqueIndex('subject', self::SUBJECT_UNIQUE_INDEX);

        // The global keys are restored only when the data still allows
        // them. A school may now run the same program code as another
        // school, which the global key cannot express.
        if ($this->hasDuplicateValues('courses', ['code'])) {
            $this->dropUniqueIndex('courses', self::LEGACY_COURSE_INDEX);
        } else {
            $this->createUniqueIndex('courses', self::LEGACY_COURSE_INDEX, ['code']);
        }

        if ($this->hasDuplicateValues('subject', ['code'])) {
            $this->dropUniqueIndex('subject', self::LEGACY_SUBJECT_INDEX);
        } else {
            $this->createUniqueIndex('subject', self::LEGACY_SUBJECT_INDEX, ['code']);
        }
    }

    /**
     * Give every course and subject the school it belongs to.
     *
     * A course takes the school of its department, a subject takes the school
     * of its course, and anything still unset falls back to the first school
     * of the installation.
     */
    private function backfillSchools(): void
    {
        if (! Schema::hasTable('courses') || ! Schema::hasColumn('courses', 'school_id')) {
            return;
        }

        if (Schema::hasTable('departments') && Schema::hasColumn('courses', 'department_id')) {
            DB::table('courses')
                ->whereNull('school_id')
                ->whereNotNull('department_id')
                ->update([
                    'school_id' => DB::raw('(SELECT d.school_id FROM departments d WHERE d.id = courses.department_id)'),
                ]);
        }

        $defaultSchoolId = null;

        if (Schema::hasTable('schools')) {
            $defaultSchoolId = DB::table('schools')->orderBy('id')->value('id');
        }

        if ($defaultSchoolId !== null) {
            DB::table('courses')->whereNull('school_id')->update(['school_id' => $defaultSchoolId]);
        }

        if (! Schema::hasTable('subject') || ! Schema::hasColumn('subject', 'school_id')) {
            return;
        }

        if (Schema::hasColumn('subject', 'course_id')) {
            DB::table('subject')
                ->whereNull('school_id')
                ->whereNotNull('course_id')
                ->update([
                    'school_id' => DB::raw('(SELECT c.school_id FROM courses c WHERE c.id = subject.course_id)'),
                ]);
        }

        if ($defaultSchoolId !== null) {
            DB::table('subject')->whereNull('school_id')->update(['school_id' => $defaultSchoolId]);
        }
    }

    /**
     * Refuse to continue when two programs still share a code in one school.
     */
    private function assertNoDuplicateCourseCodes(): void
    {
        if (! Schema::hasColumn('courses', 'school_id') || ! Schema::hasColumn('courses', 'code')) {
            return;
        }

        $duplicates = $this->duplicateKeys('courses', ['school_id', 'code']);

        if ($duplicates === []) {
            return;
        }

        throw new RuntimeException(sprintf(
            'The unique key on (school_id, code) cannot be created for courses because %d program code(s) are still used twice in the same school, for example %s. '
            .'Run "php artisan migrate" with the merge migration 2026_11_08_000001_merge_duplicate_courses applied, or give every duplicate program its own code, and deploy again.',
            $this->duplicateKeyCount('courses', ['school_id', 'code']),
            implode(', ', $duplicates),
        ));
    }

    /**
     * Refuse to continue when two subjects still share a code in one program.
     */
    private function assertNoDuplicateSubjectCodes(): void
    {
        foreach (['school_id', 'course_id', 'code'] as $column) {
            if (! Schema::hasColumn('subject', $column)) {
                return;
            }
        }

        $duplicates = $this->duplicateKeys('subject', ['school_id', 'course_id', 'code']);

        if ($duplicates === []) {
            return;
        }

        throw new RuntimeException(sprintf(
            'The unique key on (school_id, course_id, code) cannot be created for subject because %d subject code(s) are still used twice in the same program, for example %s. '
            .'Give every duplicate subject its own code, or merge them into one, and deploy again.',
            $this->duplicateKeyCount('subject', ['school_id', 'course_id', 'code']),
            implode(', ', $duplicates),
        ));
    }

    /**
     * A readable example of each duplicated key.
     *
     * @param  list<string>  $columns
     * @return list<string>
     */
    private function duplicateKeys(string $table, array $columns): array
    {
        $query = DB::table($table)
            ->select($columns)
            ->groupBy($columns)
            ->havingRaw('COUNT(*) > 1');

        foreach ($columns as $column) {
            $query->orderBy($column);
        }

        return $query->limit(5)->get()
            ->map(function (object $row) use ($columns): string {
                $parts = array_map(
                    fn (string $column): string => $column.'='.$this->describeValue($row->{$column}),
                    $columns,
                );

                return '('.implode(', ', $parts).')';
            })
            ->all();
    }

    /**
     * How many duplicated keys exist.
     *
     * @param  list<string>  $columns
     */
    private function duplicateKeyCount(string $table, array $columns): int
    {
        return DB::query()
            ->fromSub(
                DB::table($table)
                    ->select($columns)
                    ->groupBy($columns)
                    ->havingRaw('COUNT(*) > 1'),
                'duplicated_keys',
            )
            ->count();
    }

    /**
     * Whether any values repeat for the given columns.
     *
     * @param  list<string>  $columns
     */
    private function hasDuplicateValues(string $table, array $columns): bool
    {
        return DB::table($table)
            ->select($columns)
            ->groupBy($columns)
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->exists();
    }

    private function describeValue(mixed $value): string
    {
        return $value === null ? 'NULL' : (string) $value;
    }

    /**
     * Create a unique key, unless an equivalent key already exists.
     *
     * @param  list<string>  $columns
     */
    private function createUniqueIndex(string $table, string $name, array $columns): void
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        $existing = $this->indexColumns($table, $name);

        if ($existing === $columns) {
            return;
        }

        if ($existing !== null) {
            $this->dropUniqueIndex($table, $name);
        }

        $this->dropInvalidPostgresIndexes($table, [$name]);

        $grammar = DB::connection()->getQueryGrammar();

        $sql = sprintf(
            'CREATE UNIQUE INDEX %s%s ON %s (%s)',
            DB::getDriverName() === 'pgsql' ? 'CONCURRENTLY ' : '',
            $grammar->wrap($name),
            $grammar->wrapTable($table),
            implode(', ', array_map($grammar->wrap(...), $columns)),
        );

        DB::statement($sql);
    }

    /**
     * Drop a unique key, whether it is a constraint or a plain index.
     */
    private function dropUniqueIndex(string $table, string $name): void
    {
        if (! Schema::hasIndex($table, $name)) {
            return;
        }

        $grammar = DB::connection()->getQueryGrammar();

        if (DB::getDriverName() === 'pgsql') {
            if ($this->isPostgresConstraint($table, $name)) {
                DB::statement(sprintf(
                    'ALTER TABLE %s DROP CONSTRAINT %s',
                    $grammar->wrapTable($table),
                    $grammar->wrap($name),
                ));

                return;
            }

            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS '.$grammar->wrap($name));

            return;
        }

        DB::statement('DROP INDEX IF EXISTS '.$grammar->wrap($name));
    }

    /**
     * The columns of an index, or null when the index does not exist.
     *
     * @return list<string>|null
     */
    private function indexColumns(string $table, string $name): ?array
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (mb_strtolower((string) $index['name']) === mb_strtolower($name)) {
                return array_map('strval', $index['columns']);
            }
        }

        return null;
    }

    /**
     * Whether an index on PostgreSQL is backed by a table constraint.
     *
     * A constraint backed index cannot be dropped with DROP INDEX, it has to
     * be dropped with ALTER TABLE ... DROP CONSTRAINT.
     */
    private function isPostgresConstraint(string $table, string $name): bool
    {
        $row = DB::selectOne(
            <<<'SQL'
            SELECT 1
            FROM pg_constraint c
            JOIN pg_class t ON t.oid = c.conrelid
            JOIN pg_namespace n ON n.oid = t.relnamespace
            WHERE c.conname = ? AND t.relname = ? AND n.nspname = current_schema()
            SQL,
            [$name, $table],
        );

        return $row !== null;
    }

    /**
     * Drop indexes left invalid by an interrupted concurrent build.
     *
     * PostgreSQL keeps a failed CREATE INDEX CONCURRENTLY as an invalid
     * index, and the schema inspector still reports it. Without this
     * cleanup the rebuild would be skipped for good.
     *
     * @param  list<string>  $names
     */
    private function dropInvalidPostgresIndexes(string $table, array $names): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $invalid = DB::select(
            <<<'SQL'
            SELECT c.relname AS name
            FROM pg_index i
            JOIN pg_class c ON c.oid = i.indexrelid
            JOIN pg_class t ON t.oid = i.indrelid
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE t.relname = ?
              AND n.nspname = current_schema()
              AND NOT i.indisvalid
            SQL,
            [$table],
        );

        $grammar = DB::connection()->getQueryGrammar();

        foreach ($invalid as $index) {
            if (! in_array($index->name, $names, true)) {
                continue;
            }

            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS '.$grammar->wrap($index->name));
        }
    }
};
