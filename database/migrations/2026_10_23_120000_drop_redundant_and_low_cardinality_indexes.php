<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

/**
 * Removes the indexes that only duplicate the leading columns of a wider index
 * already present on the same table, plus the single-column indexes on columns
 * that can never be selective.
 *
 * Every index on a table is maintained on each write to that table, so a
 * duplicate key costs insert and update throughput without making any query
 * faster. `activity_log` is the worst offender: it carried fourteen indexes,
 * six of which were either a prefix of a composite key that was already there
 * or a single column holding a handful of distinct values.
 *
 * Two kinds of index are removed here:
 *
 * 1. Redundant keys. Index A is redundant when another index on the same table
 *    starts with the same columns, because the planner can use the wider index
 *    for every lookup that A could serve. A unique index is never redundant
 *    (it enforces a constraint), so only the non-unique twin of a unique key
 *    is dropped.
 *
 * 2. Low cardinality keys. A single-column index on a boolean, an enum or a
 *    short status string matches a large share of the table, so the planner
 *    prefers a sequential scan and the index only costs writes. Where a real
 *    query pairs that column with a selective one, the single-column index is
 *    replaced by a composite that ends in the selective column.
 */
return new class extends Migration
{
    /**
     * Non-unique indexes on the default connection whose columns are a prefix
     * of another index on the same table.
     *
     * @var array<string, array<string, list<string>>>
     */
    private const REDUNDANT = [
        'courses' => [
            'courses_school_id_index' => ['school_id'],
        ],
        'faculty' => [
            'faculty_school_id_index' => ['school_id'],
        ],
        'financial_document_issuances' => [
            'financial_document_issuances_type_index' => ['type'],
        ],
        'form_templates' => [
            'form_templates_tenant_key_index' => ['tenant_key'],
        ],
        'notifications' => [
            'notifications_notifiable_type_notifiable_id_index' => ['notifiable_type', 'notifiable_id'],
        ],
        'schools' => [
            'schools_code_index' => ['code'],
        ],
        'student_clearances' => [
            'idx_student_clearances_student_id' => ['student_id'],
            'idx_student_clearances_lookup' => ['student_id', 'academic_year', 'semester'],
        ],
        'student_enrollment' => [
            'student_enrollment_school_id_index' => ['school_id'],
        ],
        'students' => [
            'idx_students_status' => ['status', 'deleted_at'],
            'idx_students_type' => ['student_type', 'deleted_at'],
        ],
    ];

    /**
     * Single-column indexes on the default connection whose column can never
     * be selective.
     *
     * @var array<string, array<string, list<string>>>
     */
    private const LOW_CARDINALITY = [
        'financial_document_deliveries' => [
            'financial_document_deliveries_status_index' => ['status'],
        ],
        'financial_document_issuances' => [
            'financial_document_issuances_status_index' => ['status'],
        ],
        'form_invitations' => [
            'form_invitations_status_index' => ['status'],
        ],
        'form_response_links' => [
            'form_response_links_status_index' => ['status'],
        ],
        'form_responses' => [
            'form_responses_status_index' => ['status'],
        ],
        'forms' => [
            'forms_status_index' => ['status'],
        ],
        'library_books' => [
            'library_books_status_index' => ['status'],
        ],
        'library_borrow_records' => [
            'library_borrow_records_status_index' => ['status'],
        ],
        'notification_templates' => [
            'notification_templates_category_index' => ['category'],
        ],
        'schools' => [
            'schools_is_active_index' => ['is_active'],
        ],
        'statement_of_account_issuances' => [
            'statement_of_account_issuances_status_index' => ['status'],
        ],
        'student_clearances' => [
            'idx_student_clearances_is_cleared' => ['is_cleared'],
        ],
    ];

    /**
     * The activity log keeps its own table name and connection.
     *
     * @var array<string, list<string>>
     */
    private const REDUNDANT_ACTIVITY = [
        'activity_log_log_name_index' => ['log_name'],
        'activity_log_risk_level_index' => ['risk_level'],
        'activity_log_causer_index' => ['causer_type', 'causer_id'],
        'activity_log_subject_index' => ['subject_type', 'subject_id'],
    ];

    /**
     * @var array<string, list<string>>
     */
    private const LOW_CARDINALITY_ACTIVITY = [
        'activity_log_risk_score_index' => ['risk_score'],
        'activity_log_retention_hold_index' => ['retention_hold'],
    ];

    /**
     * The AI conversations keep their own table name and connection.
     *
     * @var array<string, list<string>>
     */
    private const REDUNDANT_CONVERSATIONS = [
        'agent_conversation_messages_conversation_id_index' => ['conversation_id'],
    ];

    /**
     * The mail log keeps its own table name.
     *
     * @var array<string, list<string>>
     */
    private const LOW_CARDINALITY_MAIL = [
        'mail_events_type_index' => ['type'],
    ];

    /**
     * The Pulse tables live on the connection configured by
     * `pulse.storage.database.connection`, which is not always the default one.
     *
     * @var array<string, array<string, list<string>>>
     */
    private const REDUNDANT_PULSE = [
        'pulse_entries' => [
            'pulse_entries_timestamp_index' => ['timestamp'],
        ],
        'pulse_values' => [
            'pulse_values_type_index' => ['type'],
        ],
    ];

    /**
     * @var array<string, array<string, list<string>>>
     */
    private const LOW_CARDINALITY_PULSE = [
        'pulse_aggregates' => [
            'pulse_aggregates_type_index' => ['type'],
        ],
        'pulse_entries' => [
            'pulse_entries_type_index' => ['type'],
        ],
    ];

    /**
     * Composite indexes that take the place of a dropped low cardinality key.
     *
     * Each of these serves a query that filters on the low cardinality column
     * together with a selective one, so the pair is worth maintaining where the
     * single column key was not.
     *
     * @var array<string, array{name: string, columns: list<string>}>
     */
    private const REPLACEMENTS = [
        'financial_document_deliveries' => [
            'name' => 'financial_document_deliveries_issuance_status_index',
            'columns' => ['financial_document_issuance_id', 'status'],
        ],
        'library_books' => [
            'name' => 'library_books_status_created_at_index',
            'columns' => ['status', 'created_at'],
        ],
        'library_borrow_records' => [
            'name' => 'library_borrow_records_status_due_date_index',
            'columns' => ['status', 'due_date'],
        ],
    ];

    public function up(): void
    {
        // The replacements go in first so no table is left without a usable
        // index while the migration runs.
        foreach ($this->replacementPlan() as $connection => $tables) {
            $schema = Schema::connection($connection);

            foreach ($tables as $table => $replacement) {
                if (! $schema->hasTable($table) || $schema->hasIndex($table, $replacement['name'])) {
                    continue;
                }

                $schema->table($table, function (Blueprint $blueprint) use ($replacement): void {
                    $blueprint->index($replacement['columns'], $replacement['name']);
                });
            }
        }

        foreach ($this->dropPlan() as $connection => $tables) {
            $schema = Schema::connection($connection);

            foreach ($tables as $table => $indexes) {
                if (! $schema->hasTable($table)) {
                    continue;
                }

                foreach (array_keys($indexes) as $index) {
                    if (! $schema->hasIndex($table, $index)) {
                        continue;
                    }

                    $schema->table($table, function (Blueprint $blueprint) use ($index): void {
                        $blueprint->dropIndex($index);
                    });
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->replacementPlan() as $connection => $tables) {
            $schema = Schema::connection($connection);

            foreach ($tables as $table => $replacement) {
                if (! $schema->hasTable($table) || ! $schema->hasIndex($table, $replacement['name'])) {
                    continue;
                }

                $schema->table($table, function (Blueprint $blueprint) use ($replacement): void {
                    $blueprint->dropIndex($replacement['name']);
                });
            }
        }

        foreach ($this->dropPlan() as $connection => $tables) {
            $schema = Schema::connection($connection);

            foreach ($tables as $table => $indexes) {
                if (! $schema->hasTable($table)) {
                    continue;
                }

                foreach ($indexes as $index => $columns) {
                    if ($schema->hasIndex($table, $index)) {
                        continue;
                    }

                    $schema->table($table, function (Blueprint $blueprint) use ($index, $columns): void {
                        $blueprint->index($columns, $index);
                    });
                }
            }
        }
    }

    /**
     * The indexes to drop, grouped by the connection that holds the table.
     *
     * @return array<string, array<string, array<string, list<string>>>>
     */
    private function dropPlan(): array
    {
        return $this->mergeGroups([
            [$this->defaultConnection(), self::REDUNDANT],
            [$this->defaultConnection(), self::LOW_CARDINALITY],
            [$this->activityConnection(), [$this->activityTable() => self::REDUNDANT_ACTIVITY]],
            [$this->activityConnection(), [$this->activityTable() => self::LOW_CARDINALITY_ACTIVITY]],
            [$this->conversationConnection(), [$this->conversationTable() => self::REDUNDANT_CONVERSATIONS]],
            [$this->mailConnection(), [$this->mailTable() => self::LOW_CARDINALITY_MAIL]],
            [$this->pulseConnection(), self::REDUNDANT_PULSE],
            [$this->pulseConnection(), self::LOW_CARDINALITY_PULSE],
        ]);
    }

    /**
     * The composite indexes that replace a dropped low cardinality key.
     *
     * @return array<string, array<string, array{name: string, columns: list<string>}>>
     */
    private function replacementPlan(): array
    {
        $plan = [$this->defaultConnection() => self::REPLACEMENTS];

        // The mail log keeps its own table name.
        $plan[$this->mailConnection()][$this->mailTable()] = [
            'name' => 'mail_events_type_occurred_at_index',
            'columns' => ['type', 'occurred_at'],
        ];

        return $plan;
    }

    /**
     * Merges index groups that share a connection.
     *
     * @param  list<array{0: string, 1: array<string, array<string, list<string>>>}>  $groups
     * @return array<string, array<string, array<string, list<string>>>>
     */
    private function mergeGroups(array $groups): array
    {
        $merged = [];

        foreach ($groups as [$connection, $tables]) {
            foreach ($tables as $table => $indexes) {
                $merged[$connection][$table] = array_merge($merged[$connection][$table] ?? [], $indexes);
            }
        }

        return $merged;
    }

    private function activityTable(): string
    {
        $table = config('activitylog.table_name');

        return is_string($table) && $table !== '' ? $table : 'activity_log';
    }

    private function activityConnection(): string
    {
        $modelClass = config('activitylog.activity_model');

        if (is_string($modelClass) && is_a($modelClass, Activity::class, true)) {
            $connection = (new $modelClass)->getConnectionName();

            if (is_string($connection) && $connection !== '') {
                return $connection;
            }
        }

        return $this->defaultConnection();
    }

    private function conversationTable(): string
    {
        $table = config('ai.conversations.tables.messages');

        return is_string($table) && $table !== '' ? $table : 'agent_conversation_messages';
    }

    private function conversationConnection(): string
    {
        $connection = config('ai.conversations.connection');

        return is_string($connection) && $connection !== ''
            ? $connection
            : $this->defaultConnection();
    }

    private function mailTable(): string
    {
        $table = config('mails.database.tables.events');

        return is_string($table) && $table !== '' ? $table : 'mail_events';
    }

    private function mailConnection(): string
    {
        $modelClass = config('mails.models.mail');

        if (is_string($modelClass) && class_exists($modelClass)) {
            $model = new $modelClass;

            if ($model instanceof Model) {
                $connection = $model->getConnectionName();

                if (is_string($connection) && $connection !== '') {
                    return $connection;
                }
            }
        }

        return $this->defaultConnection();
    }

    private function pulseConnection(): string
    {
        $connection = config('pulse.storage.database.connection');

        return is_string($connection) && $connection !== ''
            ? $connection
            : $this->defaultConnection();
    }

    private function defaultConnection(): string
    {
        $connection = config('database.default');

        return is_string($connection) && $connection !== '' ? $connection : 'mysql';
    }
};
