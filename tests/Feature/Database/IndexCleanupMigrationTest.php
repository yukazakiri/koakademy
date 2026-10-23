<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

const MIGRATION_PATH = 'database/migrations/2026_10_23_120000_drop_redundant_and_low_cardinality_indexes.php';

/**
 * The indexes the migration removes because a wider index on the same table
 * already starts with the same columns.
 */
const REDUNDANT_INDEXES = [
    'activity_log' => [
        'activity_log_log_name_index',
        'activity_log_risk_level_index',
        'activity_log_causer_index',
        'activity_log_subject_index',
    ],
    'agent_conversation_messages' => [
        'agent_conversation_messages_conversation_id_index',
    ],
    'courses' => [
        'courses_school_id_index',
    ],
    'faculty' => [
        'faculty_school_id_index',
    ],
    'financial_document_issuances' => [
        'financial_document_issuances_type_index',
    ],
    'form_templates' => [
        'form_templates_tenant_key_index',
    ],
    'notifications' => [
        'notifications_notifiable_type_notifiable_id_index',
    ],
    'schools' => [
        'schools_code_index',
    ],
    'student_clearances' => [
        'idx_student_clearances_student_id',
        'idx_student_clearances_lookup',
    ],
    'student_enrollment' => [
        'student_enrollment_school_id_index',
    ],
    'students' => [
        'idx_students_status',
        'idx_students_type',
    ],
];

/**
 * The single-column indexes on columns that can never be selective.
 */
const LOW_CARDINALITY_INDEXES = [
    'activity_log' => [
        'activity_log_risk_score_index',
        'activity_log_retention_hold_index',
    ],
    'financial_document_deliveries' => [
        'financial_document_deliveries_status_index',
    ],
    'financial_document_issuances' => [
        'financial_document_issuances_status_index',
    ],
    'form_invitations' => [
        'form_invitations_status_index',
    ],
    'form_response_links' => [
        'form_response_links_status_index',
    ],
    'form_responses' => [
        'form_responses_status_index',
    ],
    'forms' => [
        'forms_status_index',
    ],
    'library_books' => [
        'library_books_status_index',
    ],
    'library_borrow_records' => [
        'library_borrow_records_status_index',
    ],
    'mail_events' => [
        'mail_events_type_index',
    ],
    'notification_templates' => [
        'notification_templates_category_index',
    ],
    'schools' => [
        'schools_is_active_index',
    ],
    'statement_of_account_issuances' => [
        'statement_of_account_issuances_status_index',
    ],
    'student_clearances' => [
        'idx_student_clearances_is_cleared',
    ],
];

it('drops the redundant and low cardinality indexes', function (): void {
    foreach (REDUNDANT_INDEXES + LOW_CARDINALITY_INDEXES as $table => $indexes) {
        expect(Schema::hasTable($table))->toBeTrue();

        foreach ($indexes as $index) {
            expect(Schema::hasIndex($table, $index))->toBeFalse();
        }
    }
});

it('keeps the wider indexes that supersede the dropped ones', function (): void {
    $superseding = [
        'activity_log' => [
            'activity_log_log_created_index',
            'activity_log_risk_created_index',
            'activity_log_causer_created_index',
            'activity_log_subject_created_index',
        ],
        'agent_conversation_messages' => [
            'conversation_index',
        ],
        'courses' => [
            'courses_school_id_curriculum_kind_index',
        ],
        'faculty' => [
            'faculty_school_department_index',
        ],
        'financial_document_issuances' => [
            'financial_document_issuances_type_transaction_id_unique',
            'financial_docs_enrollment_type_issued_index',
        ],
        'form_templates' => [
            'form_templates_tenant_key_name_index',
        ],
        'notifications' => [
            'idx_notifications_unread',
        ],
        'schools' => [
            'schools_code_unique',
        ],
        'student_clearances' => [
            'student_clearances_student_id_academic_year_semester_unique',
            'idx_student_clearances_period',
        ],
        'student_enrollment' => [
            'student_enrollment_form_bc_lookup',
            'assessment_export_enrollment_lookup',
        ],
        'students' => [
            'idx_students_status_employment',
            'idx_students_status_attrition',
            'idx_students_status_academic_year',
            'idx_students_type_academic_year',
        ],
    ];

    foreach ($superseding as $table => $indexes) {
        foreach ($indexes as $index) {
            expect(Schema::hasIndex($table, $index))->toBeTrue();
        }
    }
});

it('replaces the dropped status indexes with composites that end in a selective column', function (): void {
    $replacements = [
        'financial_document_deliveries' => 'financial_document_deliveries_issuance_status_index',
        'library_books' => 'library_books_status_created_at_index',
        'library_borrow_records' => 'library_borrow_records_status_due_date_index',
        'mail_events' => 'mail_events_type_occurred_at_index',
    ];

    foreach ($replacements as $table => $index) {
        expect(Schema::hasIndex($table, $index))->toBeTrue();
    }
});

it('leaves no index that only repeats the leading columns of another index on the same table', function (): void {
    $redundant = [];

    foreach (Schema::getTables() as $table) {
        $name = $table['name'];

        if (! str_starts_with($name, 'sqlite_') && Schema::hasTable($name)) {
            $indexes = array_filter(
                Schema::getIndexes($name),
                fn (array $index): bool => ! $index['primary'] && ! $index['unique'],
            );

            foreach ($indexes as $index) {
                foreach ($indexes as $other) {
                    if ($index['name'] === $other['name']) {
                        continue;
                    }

                    if (count($other['columns']) <= count($index['columns'])) {
                        continue;
                    }

                    if (array_slice($other['columns'], 0, count($index['columns'])) === $index['columns']) {
                        $redundant[] = "{$name}.{$index['name']} is a prefix of {$other['name']}";
                    }
                }
            }
        }
    }

    expect($redundant)->toBeEmpty();
});

it('restores the dropped indexes on rollback', function (): void {
    $migration = require base_path(MIGRATION_PATH);

    if (! $migration instanceof Migration) {
        throw new RuntimeException('The index cleanup migration did not return a migration.');
    }

    if (! method_exists($migration, 'down') || ! method_exists($migration, 'up')) {
        throw new RuntimeException('The index cleanup migration is missing up() or down().');
    }

    $migration->down();

    foreach (REDUNDANT_INDEXES + LOW_CARDINALITY_INDEXES as $table => $indexes) {
        foreach ($indexes as $index) {
            expect(Schema::hasIndex($table, $index))->toBeTrue();
        }
    }

    expect(Schema::hasIndex('library_books', 'library_books_status_created_at_index'))->toBeFalse()
        ->and(Schema::hasIndex('mail_events', 'mail_events_type_occurred_at_index'))->toBeFalse();

    $migration->up();

    foreach (REDUNDANT_INDEXES + LOW_CARDINALITY_INDEXES as $table => $indexes) {
        foreach ($indexes as $index) {
            expect(Schema::hasIndex($table, $index))->toBeFalse();
        }
    }
});
