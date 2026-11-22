<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, list<array{columns: list<string>, name: string}>>
     */
    private array $indexes = [
        'students' => [
            ['columns' => ['school_id', 'deleted_at'], 'name' => 'idx_students_school_deleted'],
            ['columns' => ['email'], 'name' => 'idx_students_email'],
        ],
        'class_enrollments' => [
            ['columns' => ['student_id'], 'name' => 'idx_class_enrollments_student_id'],
            ['columns' => ['student_id', 'class_id'], 'name' => 'idx_class_enrollments_student_class'],
        ],
        'subject_enrollments' => [
            ['columns' => ['student_id', 'school_year', 'semester'], 'name' => 'idx_subject_enrollments_student_period'],
        ],
        'student_tuition' => [
            ['columns' => ['student_id', 'school_year', 'semester'], 'name' => 'idx_student_tuition_student_period'],
        ],
        'classes' => [
            ['columns' => ['school_id', 'school_year', 'semester'], 'name' => 'idx_classes_school_period'],
        ],
        'transactions' => [
            ['columns' => ['transaction_date'], 'name' => 'idx_transactions_date'],
            ['columns' => ['created_at'], 'name' => 'idx_transactions_created_at'],
            ['columns' => ['status'], 'name' => 'idx_transactions_status'],
            ['columns' => ['invoicenumber'], 'name' => 'idx_transactions_invoicenumber'],
        ],
        'student_transactions' => [
            ['columns' => ['student_id'], 'name' => 'idx_student_transactions_student_id'],
        ],
        'help_tickets' => [
            ['columns' => ['status'], 'name' => 'idx_help_tickets_status'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $tableName => $definitions) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($definitions as $definition) {
                $columns = $definition['columns'];
                $indexName = $definition['name'];

                if (! Schema::hasColumns($tableName, $columns) || Schema::hasIndex($tableName, $indexName)) {
                    continue;
                }

                Schema::table($tableName, static function (Blueprint $table) use ($columns, $indexName): void {
                    $table->index($columns, $indexName);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $tableName => $definitions) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($definitions as $definition) {
                $indexName = $definition['name'];

                if (! Schema::hasIndex($tableName, $indexName)) {
                    continue;
                }

                Schema::table($tableName, static function (Blueprint $table) use ($indexName): void {
                    $table->dropIndex($indexName);
                });
            }
        }
    }
};
