<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('adds missing performance indexes on core academic, financial, and tenant tables without redundant duplicates', function (): void {
    $addedIndexes = [
        'students' => ['idx_students_school_deleted'],
        'class_enrollments' => ['idx_class_enrollments_student_class'],
        'transactions' => ['idx_transactions_created_at', 'idx_transactions_invoicenumber'],
    ];

    foreach ($addedIndexes as $table => $indexNames) {
        expect(Schema::hasTable($table))->toBeTrue("Table [{$table}] should exist.");

        foreach ($indexNames as $indexName) {
            expect(Schema::hasIndex($table, $indexName))
                ->toBeTrue("Expected index [{$indexName}] on table [{$table}].");
        }
    }

    expect(Schema::hasIndex('students', 'students_school_id_index'))->toBeFalse();

    $coveredLeadingColumns = [
        'students' => [['school_id', 'deleted_at'], ['email']],
        'class_enrollments' => [['student_id', 'class_id'], ['student_id', 'status']],
        'subject_enrollments' => [['student_id', 'school_year', 'semester']],
        'student_tuition' => [['student_id', 'school_year', 'semester']],
        'classes' => [['school_id', 'school_year', 'semester']],
        'transactions' => [['transaction_date'], ['created_at'], ['status', 'transaction_date'], ['invoicenumber']],
        'student_transactions' => [['student_id']],
        'help_tickets' => [['status']],
    ];

    foreach ($coveredLeadingColumns as $table => $columnSets) {
        $indexes = Schema::getIndexes($table);

        foreach ($columnSets as $columns) {
            $isCovered = collect($indexes)->contains(
                static fn (array $index): bool => array_slice($index['columns'], 0, count($columns)) === $columns,
            );

            expect($isCovered)->toBeTrue(
                sprintf('Expected table [%s] to have an index covering [%s].', $table, implode(', ', $columns))
            );
        }
    }
});
