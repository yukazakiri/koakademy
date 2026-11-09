<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Cleanse orphaned foreign references to avoid constraint violation on existing data.
        $this->cleanseOrphanedRows();

        // 2. Normalise column types in students table to BIGINT UNSIGNED NULL across drivers.
        $this->normalizeStudentsColumnTypes();

        // 3. Add performance and relationship-assisting indexes.
        $this->addIndexes();

        // 4. Add database-level foreign key constraints.
        $this->addForeignKeys();

        // 5. Ensure feature_group_user primary key and foreign keys exist.
        $this->ensureFeatureGroupUserIntegrity();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropForeignKeys();
        $this->dropIndexes();
    }

    private function cleanseOrphanedRows(): void
    {
        if (Schema::hasTable('students')) {
            $sideTables = [
                ['user_id', 'users'],
                ['student_contact_id', 'student_contacts'],
                ['student_parent_info', 'student_parents_info'],
                ['student_education_id', 'student_education_info'],
                ['student_personal_id', 'students_personal_info'],
                ['document_location_id', 'document_locations'],
            ];

            foreach ($sideTables as [$column, $targetTable]) {
                if (Schema::hasColumn('students', $column) && Schema::hasTable($targetTable)) {
                    DB::table('students')
                        ->whereNotNull($column)
                        ->whereNotIn($column, DB::table($targetTable)->select('id'))
                        ->update([$column => null]);
                }
            }
        }

        if (Schema::hasTable('medical_records')) {
            if (Schema::hasColumn('medical_records', 'student_id') && Schema::hasTable('students')) {
                DB::table('medical_records')
                    ->whereNotNull('student_id')
                    ->whereNotIn('student_id', DB::table('students')->select('id'))
                    ->delete();
            }

            if (Schema::hasColumn('medical_records', 'created_by') && Schema::hasTable('users')) {
                DB::table('medical_records')
                    ->whereNotNull('created_by')
                    ->whereNotIn('created_by', DB::table('users')->select('id'))
                    ->update(['created_by' => null]);
            }

            if (Schema::hasColumn('medical_records', 'updated_by') && Schema::hasTable('users')) {
                DB::table('medical_records')
                    ->whereNotNull('updated_by')
                    ->whereNotIn('updated_by', DB::table('users')->select('id'))
                    ->update(['updated_by' => null]);
            }
        }

        if (Schema::hasTable('inventory_products')) {
            if (Schema::hasColumn('inventory_products', 'category_id') && Schema::hasTable('inventory_categories')) {
                DB::table('inventory_products')
                    ->whereNotNull('category_id')
                    ->whereNotIn('category_id', DB::table('inventory_categories')->select('id'))
                    ->update(['category_id' => null]);
            }

            if (Schema::hasColumn('inventory_products', 'supplier_id') && Schema::hasTable('inventory_suppliers')) {
                DB::table('inventory_products')
                    ->whereNotNull('supplier_id')
                    ->whereNotIn('supplier_id', DB::table('inventory_suppliers')->select('id'))
                    ->update(['supplier_id' => null]);
            }
        }

        if (Schema::hasTable('inventory_categories') && Schema::hasColumn('inventory_categories', 'parent_id')) {
            DB::table('inventory_categories')
                ->whereNotNull('parent_id')
                ->whereNotIn('parent_id', DB::table('inventory_categories')->select('id'))
                ->update(['parent_id' => null]);
        }
    }

    private function normalizeStudentsColumnTypes(): void
    {
        if (! Schema::hasTable('students')) {
            return;
        }

        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE students MODIFY user_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE students MODIFY student_contact_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE students MODIFY student_parent_info BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE students MODIFY student_education_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE students MODIFY student_personal_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE students MODIFY document_location_id BIGINT UNSIGNED NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE students ALTER COLUMN user_id TYPE BIGINT USING (user_id::bigint)');
            DB::statement('ALTER TABLE students ALTER COLUMN student_contact_id TYPE BIGINT USING (student_contact_id::bigint)');
            DB::statement('ALTER TABLE students ALTER COLUMN student_parent_info TYPE BIGINT USING (student_parent_info::bigint)');
            DB::statement('ALTER TABLE students ALTER COLUMN student_education_id TYPE BIGINT USING (student_education_id::bigint)');
            DB::statement('ALTER TABLE students ALTER COLUMN student_personal_id TYPE BIGINT USING (student_personal_id::bigint)');
            DB::statement('ALTER TABLE students ALTER COLUMN document_location_id TYPE BIGINT USING (document_location_id::bigint)');
        } else {
            Schema::table('students', function (Blueprint $table): void {
                $table->unsignedBigInteger('user_id')->nullable()->change();
                $table->unsignedBigInteger('student_contact_id')->nullable()->change();
                $table->unsignedBigInteger('student_parent_info')->nullable()->change();
                $table->unsignedBigInteger('student_education_id')->nullable()->change();
                $table->unsignedBigInteger('student_personal_id')->nullable()->change();
                $table->unsignedBigInteger('document_location_id')->nullable()->change();
            });
        }
    }

    private function addIndexes(): void
    {
        $indexes = [
            'students' => [
                'students_user_id_index' => ['user_id'],
                'students_student_contact_id_index' => ['student_contact_id'],
                'students_student_parent_info_index' => ['student_parent_info'],
                'students_student_education_id_index' => ['student_education_id'],
                'students_student_personal_id_index' => ['student_personal_id'],
                'students_document_location_id_index' => ['document_location_id'],
                'students_lrn_index' => ['lrn'],
            ],
            'medical_records' => [
                'medical_records_created_by_index' => ['created_by'],
                'medical_records_updated_by_index' => ['updated_by'],
            ],
            'inventory_products' => [
                'inventory_products_category_id_index' => ['category_id'],
                'inventory_products_supplier_id_index' => ['supplier_id'],
            ],
            'inventory_categories' => [
                'inventory_categories_parent_id_index' => ['parent_id'],
            ],
            'accounts' => [
                'accounts_person_id_index' => ['person_id'],
            ],
        ];

        foreach ($indexes as $table => $tableIndexes) {
            foreach ($tableIndexes as $indexName => $columns) {
                $this->addIndexIfMissing($table, $indexName, $columns);
            }
        }
    }

    private function addForeignKeys(): void
    {
        // students table foreign keys
        $this->addForeignKeyIfMissing('students', 'user_id', 'users', 'students_user_id_foreign', 'set null');
        $this->addForeignKeyIfMissing('students', 'student_contact_id', 'student_contacts', 'students_student_contact_id_foreign', 'set null');
        $this->addForeignKeyIfMissing('students', 'student_parent_info', 'student_parents_info', 'students_student_parent_info_foreign', 'set null');
        $this->addForeignKeyIfMissing('students', 'student_education_id', 'student_education_info', 'students_student_education_id_foreign', 'set null');
        $this->addForeignKeyIfMissing('students', 'student_personal_id', 'students_personal_info', 'students_student_personal_id_foreign', 'set null');
        $this->addForeignKeyIfMissing('students', 'document_location_id', 'document_locations', 'students_document_location_id_foreign', 'set null');

        // medical_records table foreign keys
        $this->addForeignKeyIfMissing('medical_records', 'student_id', 'students', 'medical_records_student_id_foreign', 'cascade');
        $this->addForeignKeyIfMissing('medical_records', 'created_by', 'users', 'medical_records_created_by_foreign', 'set null');
        $this->addForeignKeyIfMissing('medical_records', 'updated_by', 'users', 'medical_records_updated_by_foreign', 'set null');

        // inventory table foreign keys
        $this->addForeignKeyIfMissing('inventory_products', 'category_id', 'inventory_categories', 'inventory_products_category_id_foreign', 'set null');
        $this->addForeignKeyIfMissing('inventory_products', 'supplier_id', 'inventory_suppliers', 'inventory_products_supplier_id_foreign', 'set null');
        $this->addForeignKeyIfMissing('inventory_categories', 'parent_id', 'inventory_categories', 'inventory_categories_parent_id_foreign', 'set null');
    }

    private function ensureFeatureGroupUserIntegrity(): void
    {
        if (! Schema::hasTable('feature_group_user')) {
            return;
        }

        $hasPrimary = collect(Schema::getIndexes('feature_group_user'))->contains('primary', true);
        if (! $hasPrimary) {
            Schema::table('feature_group_user', function (Blueprint $table): void {
                $table->primary(['feature_group_id', 'user_id']);
            });
        }

        $this->addForeignKeyIfMissing('feature_group_user', 'feature_group_id', 'feature_groups', 'feature_group_user_feature_group_id_foreign', 'cascade');
        $this->addForeignKeyIfMissing('feature_group_user', 'user_id', 'users', 'feature_group_user_user_id_foreign', 'cascade');
    }

    private function dropForeignKeys(): void
    {
        $this->dropForeignKeyIfExists('students', 'user_id', 'users', 'students_user_id_foreign');
        $this->dropForeignKeyIfExists('students', 'student_contact_id', 'student_contacts', 'students_student_contact_id_foreign');
        $this->dropForeignKeyIfExists('students', 'student_parent_info', 'student_parents_info', 'students_student_parent_info_foreign');
        $this->dropForeignKeyIfExists('students', 'student_education_id', 'student_education_info', 'students_student_education_id_foreign');
        $this->dropForeignKeyIfExists('students', 'student_personal_id', 'students_personal_info', 'students_student_personal_id_foreign');
        $this->dropForeignKeyIfExists('students', 'document_location_id', 'document_locations', 'students_document_location_id_foreign');

        $this->dropForeignKeyIfExists('medical_records', 'student_id', 'students', 'medical_records_student_id_foreign');
        $this->dropForeignKeyIfExists('medical_records', 'created_by', 'users', 'medical_records_created_by_foreign');
        $this->dropForeignKeyIfExists('medical_records', 'updated_by', 'users', 'medical_records_updated_by_foreign');

        $this->dropForeignKeyIfExists('inventory_products', 'category_id', 'inventory_categories', 'inventory_products_category_id_foreign');
        $this->dropForeignKeyIfExists('inventory_products', 'supplier_id', 'inventory_suppliers', 'inventory_products_supplier_id_foreign');
        $this->dropForeignKeyIfExists('inventory_categories', 'parent_id', 'inventory_categories', 'inventory_categories_parent_id_foreign');
    }

    private function dropIndexes(): void
    {
        $indexes = [
            'students' => [
                'students_student_contact_id_index',
                'students_student_parent_info_index',
                'students_student_education_id_index',
                'students_student_personal_id_index',
                'students_document_location_id_index',
            ],
            'medical_records' => [
                'medical_records_created_by_index',
                'medical_records_updated_by_index',
            ],
            'inventory_products' => [
                'inventory_products_category_id_index',
                'inventory_products_supplier_id_index',
            ],
            'inventory_categories' => [
                'inventory_categories_parent_id_index',
            ],
            'accounts' => [
                'accounts_person_id_index',
            ],
        ];

        foreach ($indexes as $table => $tableIndexes) {
            foreach ($tableIndexes as $indexName) {
                $this->dropIndexIfExists($table, $indexName);
            }
        }
    }

    private function hasForeignKey(string $table, string $column, string $foreignTable): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        foreach (Schema::getForeignKeys($table) as $fk) {
            $columns = $fk['columns'] ?? [];
            $foreign = $fk['foreign_table'] ?? '';
            if ($columns === [$column] && $foreign === $foreignTable) {
                return true;
            }
        }

        return false;
    }

    private function addForeignKeyIfMissing(string $table, string $column, string $foreignTable, string $fkName, string $onDelete = 'set null'): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasTable($foreignTable) || ! Schema::hasColumn($table, $column) || ! Schema::hasColumn($foreignTable, 'id')) {
            return;
        }

        if (! $this->hasForeignKey($table, $column, $foreignTable)) {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $foreignTable, $fkName, $onDelete): void {
                $blueprint->foreign($column, $fkName)
                    ->references('id')
                    ->on($foreignTable)
                    ->onDelete($onDelete);
            });
        }
    }

    private function addIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        if (Schema::hasIndex($table, $indexName)) {
            return;
        }

        foreach (Schema::getIndexes($table) as $index) {
            if (($index['columns'] ?? []) === $columns) {
                return;
            }
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName, $columns): void {
            $blueprint->index($columns, $indexName);
        });
    }

    private function dropForeignKeyIfExists(string $table, string $column, string $foreignTable, string $fkName): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if ($this->hasForeignKey($table, $column, $foreignTable)) {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $fkName): void {
                if (DB::getDriverName() === 'sqlite') {
                    $blueprint->dropForeign([$column]);
                } else {
                    $blueprint->dropForeign($fkName);
                }
            });
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasIndex($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
            $blueprint->dropIndex($indexName);
        });
    }
};
