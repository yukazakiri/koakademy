<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

const HOT_PATH_MIGRATION_PATH = 'database/migrations/2026_11_03_000001_add_hot_path_indexes.php';

/**
 * The indexes DCC-23 adds, keyed by table and then by index name.
 */
const HOT_PATH_INDEXES = [
    'student_tuition' => [
        'student_tuition_enrollment_id_index' => ['enrollment_id'],
        'student_tuition_school_year_semester_index' => ['school_year', 'semester'],
        'student_tuition_status_index' => ['status'],
        'student_tuition_student_id_school_year_semester_index' => ['student_id', 'school_year', 'semester'],
    ],
    'schedule' => [
        'schedule_room_id_index' => ['room_id'],
        'schedule_day_of_week_start_time_index' => ['day_of_week', 'start_time'],
    ],
    'class_attendance_sessions' => [
        'class_attendance_sessions_class_id_session_date_index' => ['class_id', 'session_date'],
    ],
    'class_attendance_records' => [
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
 * Single-column keys replaced by a wider key in the same migration.
 */
const HOT_PATH_SUPERSEDED_INDEXES = [
    'student_tuition' => 'student_tuition_student_id_index',
    'class_enrollments' => 'class_enrollments_student_id_index',
    'subject_enrollments' => 'subject_enrollments_student_id_index',
    'classes' => 'classes_school_id_index',
];

it('adds the missing indexes on the hot tables', function (): void {
    foreach (HOT_PATH_INDEXES as $table => $indexes) {
        expect(Schema::hasTable($table))->toBeTrue();

        foreach ($indexes as $name => $columns) {
            expect(Schema::hasIndex($table, $name))->toBeTrue();

            $index = collect(Schema::getIndexes($table))->firstWhere('name', $name);

            expect($index)->not->toBeNull()
                ->and($index['columns'])->toBe($columns);
        }
    }
});

it('replaces the single column keys with wider keys that start with the same column', function (): void {
    foreach (HOT_PATH_SUPERSEDED_INDEXES as $table => $index) {
        expect(Schema::hasIndex($table, $index))->toBeFalse();
    }
});

it('is idempotent when run against a database that already has the indexes', function (): void {
    $migration = require base_path(HOT_PATH_MIGRATION_PATH);

    if (! $migration instanceof Migration) {
        throw new RuntimeException('The hot path index migration did not return a migration.');
    }

    $migration->up();

    foreach (HOT_PATH_INDEXES as $table => $indexes) {
        foreach (array_keys($indexes) as $name) {
            expect(Schema::hasIndex($table, $name))->toBeTrue();
        }
    }
});

it('restores the superseded keys on rollback', function (): void {
    $migration = require base_path(HOT_PATH_MIGRATION_PATH);

    if (! $migration instanceof Migration) {
        throw new RuntimeException('The hot path index migration did not return a migration.');
    }

    $migration->down();

    foreach (HOT_PATH_SUPERSEDED_INDEXES as $table => $index) {
        expect(Schema::hasIndex($table, $index))->toBeTrue();
    }

    foreach (HOT_PATH_INDEXES as $table => $indexes) {
        foreach (array_keys($indexes) as $name) {
            expect(Schema::hasIndex($table, $name))->toBeFalse();
        }
    }

    $migration->up();

    foreach (HOT_PATH_INDEXES as $table => $indexes) {
        foreach (array_keys($indexes) as $name) {
            expect(Schema::hasIndex($table, $name))->toBeTrue();
        }
    }
});
