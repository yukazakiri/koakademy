<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\DocumentLocation;
use App\Models\Student;
use App\Models\StudentContact;
use App\Models\StudentEducationInfo;
use App\Models\StudentParentsInfo;
use App\Models\StudentsPersonalInfo;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Inventory\Models\InventoryCategory;
use Modules\Inventory\Models\InventoryProduct;
use Modules\Inventory\Models\InventorySupplier;
use Modules\StudentMedicalRecords\Models\MedicalRecord;

/**
 * DB-006 / DCC-28: Foreign keys and relationship indexes audit tests.
 *
 * Verifies that:
 * 1. students.user_id has a foreign key to users(id) ON DELETE SET NULL and an index.
 * 2. medical_records has foreign keys to students(id) ON DELETE CASCADE and users(id) ON DELETE SET NULL.
 * 3. students profile side-tables have foreign keys ON DELETE SET NULL and performance indexes.
 * 4. inventory tables have foreign keys ON DELETE SET NULL and indexes on foreign keys.
 * 5. feature_group_user has composite primary key and foreign keys.
 * 6. accounts.person_id and students.lrn are indexed for hasOne relations.
 */
beforeEach(function (): void {
    if (DB::getDriverName() === 'sqlite') {
        DB::statement('PRAGMA foreign_keys = ON');
    }
});

it('enforces foreign key and index on students.user_id (DB-006)', function (): void {
    expect(Schema::hasColumn('students', 'user_id'))->toBeTrue()
        ->and(Schema::hasIndex('students', 'students_user_id_index'))->toBeTrue();

    $fks = collect(Schema::getForeignKeys('students'));
    $userFk = $fks->first(fn (array $fk): bool => $fk['columns'] === ['user_id']);

    expect($userFk)->not->toBeNull()
        ->and($userFk['foreign_table'])->toBe('users')
        ->and($userFk['on_delete'])->toBe('set null');

    // Deleting a user sets students.user_id to null
    $user = User::factory()->create();
    $student = Student::factory()->create(['user_id' => $user->id]);

    expect($student->fresh()->user_id)->toBe($user->id);

    $user->forceDelete();

    expect($student->fresh()->user_id)->toBeNull();

    // Inserting an invalid user_id violates foreign key constraint
    expect(fn () => DB::table('students')->insert([
        'first_name' => 'Invalid',
        'last_name' => 'FK',
        'birth_date' => '2005-01-01',
        'gender' => 'male',
        'user_id' => 999999999,
    ]))->toThrow(QueryException::class);
});

it('enforces foreign keys and indexes on medical_records (DB-006)', function (): void {
    expect(Schema::hasTable('medical_records'))->toBeTrue()
        ->and(Schema::hasIndex('medical_records', 'medical_records_student_id_record_type_index'))->toBeTrue()
        ->and(Schema::hasIndex('medical_records', 'medical_records_created_by_index'))->toBeTrue()
        ->and(Schema::hasIndex('medical_records', 'medical_records_updated_by_index'))->toBeTrue();

    $fks = collect(Schema::getForeignKeys('medical_records'));

    $studentFk = $fks->first(fn (array $fk): bool => $fk['columns'] === ['student_id']);
    expect($studentFk)->not->toBeNull()
        ->and($studentFk['foreign_table'])->toBe('students')
        ->and($studentFk['on_delete'])->toBe('cascade');

    $createdFk = $fks->first(fn (array $fk): bool => $fk['columns'] === ['created_by']);
    expect($createdFk)->not->toBeNull()
        ->and($createdFk['foreign_table'])->toBe('users')
        ->and($createdFk['on_delete'])->toBe('set null');

    $updatedFk = $fks->first(fn (array $fk): bool => $fk['columns'] === ['updated_by']);
    expect($updatedFk)->not->toBeNull()
        ->and($updatedFk['foreign_table'])->toBe('users')
        ->and($updatedFk['on_delete'])->toBe('set null');

    // Deleting student cascades to medical_records
    $student = Student::factory()->create();
    $creator = User::factory()->create();
    $record = MedicalRecord::create([
        'student_id' => $student->id,
        'record_type' => 'checkup',
        'title' => 'Routine Physical',
        'visit_date' => now()->toDateString(),
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
    ]);

    expect(MedicalRecord::whereKey($record->id)->exists())->toBeTrue();

    $student->forceDelete();

    expect(MedicalRecord::whereKey($record->id)->exists())->toBeFalse();

    // Deleting creator nullifies created_by and updated_by
    $student2 = Student::factory()->create();
    $creator2 = User::factory()->create();
    $record2 = MedicalRecord::create([
        'student_id' => $student2->id,
        'record_type' => 'checkup',
        'title' => 'Dental Exam',
        'visit_date' => now()->toDateString(),
        'created_by' => $creator2->id,
        'updated_by' => $creator2->id,
    ]);

    $creator2->forceDelete();

    expect($record2->fresh()->created_by)->toBeNull()
        ->and($record2->fresh()->updated_by)->toBeNull();
});

it('enforces foreign keys and indexes on students profile side-tables (DB-006)', function (): void {
    $expectedIndexes = [
        'students_student_contact_id_index',
        'students_student_parent_info_index',
        'students_student_education_id_index',
        'students_student_personal_id_index',
        'students_document_location_id_index',
    ];

    foreach ($expectedIndexes as $index) {
        expect(Schema::hasIndex('students', $index))->toBeTrue();
    }

    $fks = collect(Schema::getForeignKeys('students'));

    $expectedFks = [
        ['student_contact_id', 'student_contacts'],
        ['student_parent_info', 'student_parents_info'],
        ['student_education_id', 'student_education_info'],
        ['student_personal_id', 'students_personal_info'],
        ['document_location_id', 'document_locations'],
    ];

    foreach ($expectedFks as [$column, $target]) {
        $fk = $fks->first(fn (array $k): bool => $k['columns'] === [$column]);
        expect($fk)->not->toBeNull()
            ->and($fk['foreign_table'])->toBe($target)
            ->and($fk['on_delete'])->toBe('set null');
    }

    // Deleting side-table records nullifies corresponding column on student
    $contact = StudentContact::create(['personal_contact' => '09123456789']);
    $personal = StudentsPersonalInfo::create(['place_of_birth' => 'Quezon City']);
    $parent = StudentParentsInfo::create(['father_name' => 'Parent Name']);
    $edu = StudentEducationInfo::create(['elementary_school' => 'School Name']);
    $doc = DocumentLocation::create(['picture_1x1' => 'photo.jpg']);

    $student = Student::factory()->create([
        'student_contact_id' => $contact->id,
        'student_personal_id' => $personal->id,
        'student_parent_info' => $parent->id,
        'student_education_id' => $edu->id,
        'document_location_id' => $doc->id,
    ]);

    $contact->delete();
    $personal->delete();
    $parent->delete();
    $edu->delete();
    $doc->delete();

    $freshStudent = $student->fresh();
    expect($freshStudent->student_contact_id)->toBeNull()
        ->and($freshStudent->student_personal_id)->toBeNull()
        ->and($freshStudent->student_parent_info)->toBeNull()
        ->and($freshStudent->student_education_id)->toBeNull()
        ->and($freshStudent->document_location_id)->toBeNull();

    // Verify inverse relationships on fresh models
    $contact2 = StudentContact::create(['personal_contact' => '09998887777']);
    $studentWithContact = Student::factory()->create(['student_contact_id' => $contact2->id]);
    expect($contact2->student)->not->toBeNull()
        ->and($contact2->student->id)->toBe($studentWithContact->id);
});

it('enforces foreign keys and indexes on inventory tables (DB-006)', function (): void {
    expect(Schema::hasIndex('inventory_products', 'inventory_products_category_id_index'))->toBeTrue()
        ->and(Schema::hasIndex('inventory_products', 'inventory_products_supplier_id_index'))->toBeTrue()
        ->and(Schema::hasIndex('inventory_categories', 'inventory_categories_parent_id_index'))->toBeTrue();

    $productFks = collect(Schema::getForeignKeys('inventory_products'));

    $catFk = $productFks->first(fn (array $k): bool => $k['columns'] === ['category_id']);
    expect($catFk)->not->toBeNull()
        ->and($catFk['foreign_table'])->toBe('inventory_categories')
        ->and($catFk['on_delete'])->toBe('set null');

    $supFk = $productFks->first(fn (array $k): bool => $k['columns'] === ['supplier_id']);
    expect($supFk)->not->toBeNull()
        ->and($supFk['foreign_table'])->toBe('inventory_suppliers')
        ->and($supFk['on_delete'])->toBe('set null');

    $catTableFks = collect(Schema::getForeignKeys('inventory_categories'));
    $parentFk = $catTableFks->first(fn (array $k): bool => $k['columns'] === ['parent_id']);
    expect($parentFk)->not->toBeNull()
        ->and($parentFk['foreign_table'])->toBe('inventory_categories')
        ->and($parentFk['on_delete'])->toBe('set null');

    // Deleting category/supplier nullifies product references
    $category = InventoryCategory::create(['name' => 'Electronics', 'slug' => 'electronics']);
    $supplier = InventorySupplier::create(['name' => 'Supplier Co']);
    $product = InventoryProduct::create([
        'name' => 'Microscope',
        'sku' => 'MIC-001',
        'category_id' => $category->id,
        'supplier_id' => $supplier->id,
    ]);

    $category->delete();
    $supplier->delete();

    expect($product->fresh()->category_id)->toBeNull()
        ->and($product->fresh()->supplier_id)->toBeNull();
});

it('enforces composite primary key on feature_group_user (DB-006)', function (): void {
    expect(Schema::hasTable('feature_group_user'))->toBeTrue();

    $primaryIndex = collect(Schema::getIndexes('feature_group_user'))->firstWhere('primary', true);
    expect($primaryIndex)->not->toBeNull()
        ->and($primaryIndex['columns'])->toBe(['feature_group_id', 'user_id']);

    $fks = collect(Schema::getForeignKeys('feature_group_user'));
    expect($fks->firstWhere('columns', ['feature_group_id'])['foreign_table'])->toBe('feature_groups')
        ->and($fks->firstWhere('columns', ['user_id'])['foreign_table'])->toBe('users');
});

it('indexes accounts.person_id and students.lrn for hasOne relation performance (DB-006)', function (): void {
    expect(Schema::hasIndex('accounts', 'accounts_person_id_index'))->toBeTrue()
        ->and(Schema::hasIndex('students', 'students_lrn_index'))->toBeTrue();

    // Verify index works with Account::person_id lookup
    $account = Account::factory()->create([
        'person_id' => 4567,
    ]);

    expect(Account::where('person_id', 4567)->value('id'))->toBe($account->id);
});

it('rolls back and re-applies migration cleanly', function (): void {
    $migration = require database_path('migrations/2026_11_09_000001_add_missing_foreign_keys_and_profile_indexes.php');

    // Test down()
    $migration->down();

    $studentsFksDown = collect(Schema::getForeignKeys('students'));
    expect($studentsFksDown->firstWhere('columns', ['user_id']))->toBeNull()
        ->and($studentsFksDown->firstWhere('columns', ['student_contact_id']))->toBeNull()
        ->and(Schema::hasIndex('accounts', 'accounts_person_id_index'))->toBeFalse();

    // Test up()
    $migration->up();

    $studentsFksUp = collect(Schema::getForeignKeys('students'));
    expect($studentsFksUp->firstWhere('columns', ['user_id']))->not->toBeNull()
        ->and($studentsFksUp->firstWhere('columns', ['student_contact_id']))->not->toBeNull()
        ->and(Schema::hasIndex('accounts', 'accounts_person_id_index'))->toBeTrue();
});
