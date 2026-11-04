<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the indexes missing from the pre-2024 schema core (DCC-23 / DB-001).
 *
 * Eight tables on the hottest read and write paths had zero or one index.
 * `student_tuition` in particular had none beyond `student_id`, so the
 * `SELECT … FOR UPDATE WHERE enrollment_id = ?` in TuitionAdjustmentService
 * locked the whole table for the duration of every enrollment.
 *
 * Every key is built only when an equivalent index is absent, so installs
 * that already received part of this work from an earlier migration (for
 * example `2026_10_26_000001_normalize_student_id_columns_and_indexes`) are
 * not given duplicate keys.
 *
 * On PostgreSQL the keys are built with CREATE INDEX CONCURRENTLY so a
 * deployment does not block writes to the financial and enrollment tables
 * while a large index is built. CONCURRENTLY cannot run inside a
 * transaction, so this migration opts out of the transaction wrapper.
 * Indexes left invalid by an interrupted build are removed before the
 * retry, which makes a rerun resumable.
 */
return new class extends Migration
{
    /**
     * Internal registry of the indexes this migration created.
     *
     * Rollback must not remove an index that existed before the migration
     * ran. The registry is written before each build and read in down(), so
     * ownership survives the separate process a rollback runs in.
     */
    private const REGISTRY_TABLE = 'migration_index_registry';

    private const MIGRATION_NAME = '2026_11_03_000001_add_hot_path_indexes';

    /**
     * Indexes to create, keyed by table and then by index name.
     *
     * @var array<string, array<string, list<string>>>
     */
    private const INDEXES = [
        'student_tuition' => [
            'student_tuition_enrollment_id_index' => ['enrollment_id'],
            'student_tuition_school_year_semester_index' => ['school_year', 'semester'],
            'student_tuition_status_index' => ['status'],
            'student_tuition_student_id_school_year_semester_index' => ['student_id', 'school_year', 'semester'],
        ],
        'schedule' => [
            // `idx_schedule_class_day` already starts with class_id, so it
            // serves standalone class lookups without another key.
            'schedule_room_id_index' => ['room_id'],
            'schedule_day_of_week_start_time_index' => ['day_of_week', 'start_time'],
        ],
        'class_attendance_sessions' => [
            'class_attendance_sessions_class_id_session_date_index' => ['class_id', 'session_date'],
        ],
        'class_attendance_records' => [
            // The unique (class_attendance_session_id, class_enrollment_id)
            // key cannot serve a standalone class_enrollment_id lookup,
            // because that column is second in the key.
            'class_attendance_records_student_id_index' => ['student_id'],
            'class_attendance_records_class_id_index' => ['class_id'],
            'class_attendance_records_class_enrollment_id_index' => ['class_enrollment_id'],
        ],
        'class_enrollments' => [
            'class_enrollments_class_id_index' => ['class_id'],
            'class_enrollments_verified_by_index' => ['verified_by'],
            'class_enrollments_student_id_status_index' => ['student_id', 'status'],
        ],
        'subject_enrollments' => [
            'subject_enrollments_class_id_index' => ['class_id'],
            'subject_enrollments_enrollment_id_index' => ['enrollment_id'],
            'subject_enrollments_subject_id_index' => ['subject_id'],
            'subject_enrollments_student_id_school_year_semester_index' => ['student_id', 'school_year', 'semester'],
        ],
        'student_transactions' => [
            'student_transactions_transaction_id_index' => ['transaction_id'],
        ],
        'admin_transactions' => [
            'admin_transactions_admin_id_index' => ['admin_id'],
            'admin_transactions_transaction_id_index' => ['transaction_id'],
            'admin_transactions_status_index' => ['status'],
        ],
        'transactions' => [
            'transactions_transaction_date_index' => ['transaction_date'],
            'transactions_status_transaction_date_index' => ['status', 'transaction_date'],
            'transactions_user_id_index' => ['user_id'],
            'transactions_transaction_number_index' => ['transaction_number'],
        ],
        'classes' => [
            'classes_school_year_semester_index' => ['school_year', 'semester'],
            'classes_school_id_school_year_semester_index' => ['school_id', 'school_year', 'semester'],
            'classes_section_index' => ['section'],
            'classes_subject_code_index' => ['subject_code'],
            'classes_faculty_id_index' => ['faculty_id'],
        ],
        'students' => [
            'students_lrn_index' => ['lrn'],
            'students_email_index' => ['email'],
            'students_last_name_first_name_index' => ['last_name', 'first_name'],
            'students_user_id_index' => ['user_id'],
        ],
        'faculty' => [
            'faculty_faculty_id_number_index' => ['faculty_id_number'],
        ],
        'class_posts' => [
            'class_posts_class_id_index' => ['class_id'],
            'class_posts_status_due_date_index' => ['status', 'due_date'],
        ],
        'help_tickets' => [
            'help_tickets_user_id_index' => ['user_id'],
            'help_tickets_status_index' => ['status'],
        ],
        'additional_fees' => [
            'additional_fees_enrollment_id_index' => ['enrollment_id'],
        ],
    ];

    /**
     * Single-column keys that a wider key above fully replaces.
     *
     * Each of these is a prefix of the new composite, so keeping both would
     * only add write amplification. The wider key serves every lookup the
     * old one served.
     *
     * @var array<string, array<string, list<string>>>
     */
    private const SUPERSEDED = [
        'student_tuition' => [
            'student_tuition_student_id_index' => ['student_id'],
        ],
        'class_enrollments' => [
            'class_enrollments_student_id_index' => ['student_id'],
        ],
        'subject_enrollments' => [
            'subject_enrollments_student_id_index' => ['student_id'],
        ],
        'classes' => [
            'classes_school_id_index' => ['school_id'],
        ],
    ];

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
        $this->ensureRegistry();

        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $this->dropInvalidPostgresIndexes($table, array_keys($indexes));

            foreach ($indexes as $name => $columns) {
                if ($this->missingColumns($table, $columns) !== []) {
                    continue;
                }

                if (Schema::hasIndex($table, $name) || Schema::hasIndex($table, $columns)) {
                    continue;
                }

                $this->recordIndexOwnership($table, $name);
                $this->createIndex($table, $name, $columns);
            }
        }

        foreach (self::SUPERSEDED as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $name => $columns) {
                $this->dropIndex($table, $name, $columns);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->ensureRegistry();

        // Restore the single-column keys before removing their wider
        // replacements so no lookup is left without a usable key.
        foreach (self::SUPERSEDED as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $this->dropInvalidPostgresIndexes($table, array_keys($indexes));

            foreach ($indexes as $name => $columns) {
                if ($this->missingColumns($table, $columns) !== []) {
                    continue;
                }

                $this->createIndex($table, $name, $columns);
            }
        }

        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $name => $columns) {
                if (! $this->ownsIndex($name)) {
                    continue;
                }

                $this->dropIndex($table, $name, $columns);
                $this->forgetIndexOwnership($name);
            }
        }
    }

    /**
     * Create an index. Callers have already checked that no equivalent key
     * exists, so a name collision is a real schema problem and must fail
     * loudly rather than leave the table without the intended key.
     *
     * @param  list<string>  $columns
     */
    private function createIndex(string $table, string $name, array $columns): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $name): void {
                $blueprint->index($columns, $name);
            });

            return;
        }

        $grammar = DB::connection()->getQueryGrammar();

        DB::statement(sprintf(
            'CREATE INDEX CONCURRENTLY %s ON %s (%s)',
            $grammar->wrap($name),
            $grammar->wrapTable($table),
            implode(', ', array_map($grammar->wrap(...), $columns)),
        ));
    }

    /**
     * Drop an index, but only when it still has the columns expected here.
     *
     * The column check prevents a rollback from removing an index that was
     * already present, under the same name, with a different shape.
     *
     * @param  list<string>  $columns
     */
    private function dropIndex(string $table, string $name, array $columns): void
    {
        $existing = null;

        foreach (Schema::getIndexes($table) as $index) {
            if ($index['name'] === $name) {
                $existing = $index['columns'];

                break;
            }
        }

        if ($existing !== $columns) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS '.DB::connection()->getQueryGrammar()->wrap($name));

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name): void {
            $blueprint->dropIndex($name);
        });
    }

    /**
     * Drop indexes left invalid by an interrupted concurrent build.
     *
     * PostgreSQL keeps a failed CREATE INDEX CONCURRENTLY as an invalid index,
     * and the schema inspector still reports it. Without this cleanup,
     * `hasIndex()` would see the invalid key and skip the rebuild for good.
     *
     * Only the names this migration manages are touched: another invalid
     * index, in particular an invalid unique index, may belong to someone
     * else and must keep its constraint.
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

    /**
     * Create the ownership registry when this is the first run.
     */
    private function ensureRegistry(): void
    {
        if (Schema::hasTable(self::REGISTRY_TABLE)) {
            return;
        }

        Schema::create(self::REGISTRY_TABLE, function (Blueprint $table): void {
            $table->id();
            $table->string('migration', 191);
            $table->string('table_name', 191);
            $table->string('index_name', 191);
            $table->timestamps();

            $table->unique(['migration', 'index_name']);
        });
    }

    /**
     * Mark an index as created by this migration before building it.
     *
     * Recording first means a build that fails halfway is still owned, so
     * the next run and every rollback handle it.
     */
    private function recordIndexOwnership(string $table, string $name): void
    {
        DB::table(self::REGISTRY_TABLE)->insertOrIgnore([
            'migration' => self::MIGRATION_NAME,
            'table_name' => $table,
            'index_name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ownsIndex(string $name): bool
    {
        return DB::table(self::REGISTRY_TABLE)
            ->where('migration', self::MIGRATION_NAME)
            ->where('index_name', $name)
            ->exists();
    }

    private function forgetIndexOwnership(string $name): void
    {
        DB::table(self::REGISTRY_TABLE)
            ->where('migration', self::MIGRATION_NAME)
            ->where('index_name', $name)
            ->delete();
    }

    /**
     * Columns from the key that the table does not have.
     *
     * @param  list<string>  $columns
     * @return list<string>
     */
    private function missingColumns(string $table, array $columns): array
    {
        return array_values(array_filter(
            $columns,
            fn (string $column): bool => ! Schema::hasColumn($table, $column),
        ));
    }
};
