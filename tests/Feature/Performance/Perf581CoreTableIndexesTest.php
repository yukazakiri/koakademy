<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('adds performance indexes on core academic, financial, and tenant tables', function (): void {
    $expected = [
        'students' => ['idx_students_school_deleted', 'idx_students_email'],
        'class_enrollments' => ['idx_class_enrollments_student_id', 'idx_class_enrollments_student_class'],
        'subject_enrollments' => ['idx_subject_enrollments_student_period'],
        'student_tuition' => ['idx_student_tuition_student_period'],
        'classes' => ['idx_classes_school_period'],
        'transactions' => ['idx_transactions_date', 'idx_transactions_created_at', 'idx_transactions_status', 'idx_transactions_invoicenumber'],
        'student_transactions' => ['idx_student_transactions_student_id'],
        'help_tickets' => ['idx_help_tickets_status'],
    ];

    foreach ($expected as $table => $indexNames) {
        expect(Schema::hasTable($table))->toBeTrue("Table [{$table}] should exist.");

        foreach ($indexNames as $indexName) {
            expect(Schema::hasIndex($table, $indexName))
                ->toBeTrue("Expected index [{$indexName}] on table [{$table}].");
        }
    }
});
