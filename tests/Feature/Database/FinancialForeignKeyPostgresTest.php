<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $database = getenv('FINANCIAL_MIGRATION_PG_DATABASE');
    if (! is_string($database) || ! str_starts_with($database, 'test_')) {
        $this->markTestSkipped('Set FINANCIAL_MIGRATION_PG_* to a dedicated PostgreSQL test database named test_*.');
    }
    config(['database.connections.financial_migration_test' => [
        'driver' => 'pgsql', 'host' => getenv('FINANCIAL_MIGRATION_PG_HOST') ?: '127.0.0.1',
        'port' => getenv('FINANCIAL_MIGRATION_PG_PORT') ?: '5432', 'database' => $database,
        'username' => getenv('FINANCIAL_MIGRATION_PG_USERNAME') ?: 'postgres',
        'password' => getenv('FINANCIAL_MIGRATION_PG_PASSWORD') ?: '',
        'charset' => 'utf8', 'prefix' => '', 'schema' => 'public', 'sslmode' => 'prefer',
    ]]);
    DB::setDefaultConnection('financial_migration_test');
    if (Schema::getTableListing() !== []) {
        DB::setDefaultConnection('sqlite');
        throw new RuntimeException('The PostgreSQL migration test database must be empty; no existing tables were changed.');
    }
    Schema::create('student_enrollment', fn (Blueprint $table) => $table->id());
    $this->financialPostgresTablesCreated = true;
    Schema::create('student_tuition', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('enrollment_id')->nullable();
        $table->string('note')->nullable();
    });
});

afterEach(function (): void {
    if (($this->financialPostgresTablesCreated ?? false) && DB::getDefaultConnection() === 'financial_migration_test') {
        Schema::dropIfExists('student_tuition');
        Schema::dropIfExists('student_enrollment');
        DB::setDefaultConnection('sqlite');
        DB::purge('financial_migration_test');
    }
});

function postgresFinancialConstraint(): ?array
{
    return collect(Schema::getForeignKeys('student_tuition'))->first(fn (array $foreignKey): bool => $foreignKey['columns'] === ['enrollment_id']);
}

it('repairs missing or custom named PostgreSQL foreign keys without losing tuition data', function (string $state): void {
    if ($state !== 'missing') {
        Schema::table('student_tuition', fn (Blueprint $table) => $table->foreign('enrollment_id', 'old_custom_enrollment_fk')->references('id')->on('student_enrollment')->onUpdate('cascade')->onDelete($state));
    }
    $enrollmentId = DB::table('student_enrollment')->insertGetId([]);
    DB::table('student_tuition')->insert(['enrollment_id' => $enrollmentId, 'note' => 'preserve me']);
    $migration = require database_path('migrations/2026_10_26_000001_restrict_financial_and_audit_foreign_keys.php');
    $migration->up();
    $migration->up();
    $constraint = postgresFinancialConstraint();
    expect($constraint['on_delete'])->toBe('restrict')
        ->and($constraint['foreign_table'])->toBe('student_enrollment')
        ->and($constraint['on_update'])->toBe($state === 'missing' ? 'no action' : 'cascade')
        ->and(DB::table('student_tuition')->value('note'))->toBe('preserve me');
    if ($state === 'restrict') {
        expect($constraint['name'])->toBe('old_custom_enrollment_fk');
    }
    expect(fn () => DB::table('student_enrollment')->where('id', $enrollmentId)->delete())->toThrow(QueryException::class);
})->with(['missing', 'cascade', 'restrict']);

it('repairs custom and missing constraints across all financial tables', function (): void {
    foreach (['users', 'transactions', 'students', 'schools'] as $tableName) {
        Schema::create($tableName, fn (Blueprint $table) => $table->id());
    }
    foreach ([
        'admin_transactions' => ['admin_id' => 'users', 'transaction_id' => 'transactions'],
        'student_transactions' => ['transaction_id' => 'transactions'],
        'statement_of_account_issuances' => ['student_id' => 'students'],
        'payment_allocations' => ['transaction_id' => 'transactions', 'student_id' => 'students'],
        'additional_fees' => ['enrollment_id' => 'student_enrollment'],
        'assessment_revisions' => ['student_tuition_id' => 'student_tuition', 'student_enrollment_id' => 'student_enrollment'],
        'student_tuition_update_requests' => ['submitted_by_user_id' => 'users', 'student_id' => 'students'],
        'general_settings' => ['school_id' => 'schools'],
    ] as $tableName => $relationships) {
        Schema::create($tableName, function (Blueprint $table) use ($tableName, $relationships): void {
            $table->id();
            foreach ($relationships as $column => $target) {
                $table->foreignId($column)->nullable();
                // Alternate a legacy custom name and a completely absent FK.
                if (! str_contains($column, 'transaction')) {
                    $table->foreign($column, 'legacy_'.mb_substr(hash('sha256', $tableName.$column), 0, 16))->references('id')->on($target)->cascadeOnDelete();
                }
            }
        });
    }
    $migration = require database_path('migrations/2026_10_26_000001_restrict_financial_and_audit_foreign_keys.php');
    try {
        $migration->up();
        $migration->up();
        foreach ([
            'student_tuition' => ['enrollment_id'], 'admin_transactions' => ['admin_id', 'transaction_id'],
            'student_transactions' => ['transaction_id'], 'statement_of_account_issuances' => ['student_id'],
            'payment_allocations' => ['transaction_id', 'student_id'], 'additional_fees' => ['enrollment_id'],
            'assessment_revisions' => ['student_tuition_id', 'student_enrollment_id'],
            'student_tuition_update_requests' => ['submitted_by_user_id', 'student_id'],
            'general_settings' => ['school_id'],
        ] as $tableName => $columns) {
            foreach ($columns as $column) {
                $constraint = collect(Schema::getForeignKeys($tableName))->first(fn (array $key): bool => $key['columns'] === [$column]);
                expect($constraint['on_delete'])->toBe($tableName === 'general_settings' ? 'set null' : 'restrict');
            }
        }
    } finally {
        foreach (['admin_transactions', 'student_transactions', 'statement_of_account_issuances', 'payment_allocations', 'additional_fees', 'assessment_revisions', 'student_tuition_update_requests', 'general_settings', 'users', 'transactions', 'students', 'schools'] as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }
});

it('does not discard or delete orphaned PostgreSQL tuition records', function (): void {
    DB::table('student_tuition')->insert(['enrollment_id' => 987654, 'note' => 'review']);
    $migration = require database_path('migrations/2026_10_26_000001_restrict_financial_and_audit_foreign_keys.php');
    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'existing rows reference missing');
    expect(postgresFinancialConstraint())->toBeNull()
        ->and(DB::table('student_tuition')->value('note'))->toBe('review');
});

it('refuses a PostgreSQL foreign key that targets a different table', function (): void {
    Schema::create('unexpected_students', fn (Blueprint $table) => $table->id());
    Schema::table('student_tuition', fn (Blueprint $table) => $table->foreign('enrollment_id', 'wrong_legacy_fk')->references('id')->on('unexpected_students')->cascadeOnDelete());
    $migration = require database_path('migrations/2026_10_26_000001_restrict_financial_and_audit_foreign_keys.php');
    try {
        expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'different relationship');
        expect(postgresFinancialConstraint()['name'])->toBe('wrong_legacy_fk');
    } finally {
        Schema::table('student_tuition', fn (Blueprint $table) => $table->dropForeign('wrong_legacy_fk'));
        Schema::dropIfExists('unexpected_students');
    }
});

it('refuses to replace a conflicting composite PostgreSQL foreign key', function (): void {
    Schema::table('student_enrollment', function (Blueprint $table): void {
        $table->unsignedBigInteger('campus_id')->nullable();
        $table->unique(['id', 'campus_id'], 'composite_test_pair');
    });
    Schema::table('student_tuition', function (Blueprint $table): void {
        $table->unsignedBigInteger('second_enrollment_id')->nullable();
    });
    DB::statement('ALTER TABLE student_tuition ADD CONSTRAINT composite_legacy_fk FOREIGN KEY (enrollment_id, second_enrollment_id) REFERENCES student_enrollment (id, campus_id)');
    $migration = require database_path('migrations/2026_10_26_000001_restrict_financial_and_audit_foreign_keys.php');
    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'uses multiple columns');
});
