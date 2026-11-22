<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Only genuinely missing, non-redundant indexes not already covered by
     * earlier migrations (e.g. 2026_11_03_000001_add_hot_path_indexes).
     *
     * @var array<string, list<array{columns: list<string>, name: string, supersedes?: string, supersedes_columns?: list<string>}>>
     */
    private array $indexes = [
        'students' => [
            [
                'columns' => ['school_id', 'deleted_at'],
                'name' => 'idx_students_school_deleted',
                'supersedes' => 'students_school_id_index',
                'supersedes_columns' => ['school_id'],
            ],
        ],
        'class_enrollments' => [
            ['columns' => ['student_id', 'class_id'], 'name' => 'idx_class_enrollments_student_class'],
        ],
        'transactions' => [
            ['columns' => ['created_at'], 'name' => 'idx_transactions_created_at'],
            ['columns' => ['invoicenumber'], 'name' => 'idx_transactions_invoicenumber'],
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

                if (! Schema::hasColumns($tableName, $columns)) {
                    continue;
                }

                if (! Schema::hasIndex($tableName, $indexName) && ! $this->hasIndexWithColumns($tableName, $columns)) {
                    Schema::table($tableName, static function (Blueprint $table) use ($columns, $indexName): void {
                        $table->index($columns, $indexName);
                    });
                }

                $superseded = $definition['supersedes'] ?? null;

                if ($superseded !== null && Schema::hasIndex($tableName, $superseded)) {
                    Schema::table($tableName, static function (Blueprint $table) use ($superseded): void {
                        $table->dropIndex($superseded);
                    });
                }
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
                $superseded = $definition['supersedes'] ?? null;
                $supersededColumns = $definition['supersedes_columns'] ?? null;

                if (
                    $superseded !== null
                    && $supersededColumns !== null
                    && Schema::hasColumns($tableName, $supersededColumns)
                    && ! Schema::hasIndex($tableName, $superseded)
                ) {
                    Schema::table($tableName, static function (Blueprint $table) use ($supersededColumns, $superseded): void {
                        $table->index($supersededColumns, $superseded);
                    });
                }

                if (! Schema::hasIndex($tableName, $indexName)) {
                    continue;
                }

                Schema::table($tableName, static function (Blueprint $table) use ($indexName): void {
                    $table->dropIndex($indexName);
                });
            }
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function hasIndexWithColumns(string $tableName, array $columns): bool
    {
        foreach (Schema::getIndexes($tableName) as $index) {
            if ($index['columns'] === $columns) {
                return true;
            }
        }

        return false;
    }
};
