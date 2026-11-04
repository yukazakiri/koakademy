<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PostgreSQL-only coverage for the concurrent index retry path.
 *
 * A failed CREATE INDEX CONCURRENTLY leaves an invalid index behind. The
 * migration must remove that leftover before rebuilding, otherwise the
 * schema inspector keeps seeing it and the key is never repaired.
 *
 * The dedicated test database must be empty and named test_*, matching the
 * other PostgreSQL migration tests.
 */
beforeEach(function (): void {
    $database = getenv('FINANCIAL_MIGRATION_PG_DATABASE');

    if (! is_string($database) || ! str_starts_with($database, 'test_')) {
        $this->markTestSkipped('Set FINANCIAL_MIGRATION_PG_* to a dedicated PostgreSQL test database named test_*.');
    }

    config(['database.connections.hot_path_indexes_test' => [
        'driver' => 'pgsql', 'host' => getenv('FINANCIAL_MIGRATION_PG_HOST') ?: '127.0.0.1',
        'port' => getenv('FINANCIAL_MIGRATION_PG_PORT') ?: '5432', 'database' => $database,
        'username' => getenv('FINANCIAL_MIGRATION_PG_USERNAME') ?: 'postgres',
        'password' => getenv('FINANCIAL_MIGRATION_PG_PASSWORD') ?: '',
        'charset' => 'utf8', 'prefix' => '', 'schema' => 'public', 'sslmode' => 'prefer',
    ]]);

    DB::setDefaultConnection('hot_path_indexes_test');

    if (Schema::getTableListing() !== []) {
        DB::setDefaultConnection('sqlite');

        throw new RuntimeException('The PostgreSQL migration test database must be empty; no existing tables were changed.');
    }

    Schema::create('student_tuition', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('enrollment_id')->nullable();
    });

    $this->hotPathTablesCreated = true;
});

afterEach(function (): void {
    if (($this->hotPathTablesCreated ?? false) && DB::getDefaultConnection() === 'hot_path_indexes_test') {
        Schema::dropIfExists('migration_index_registry');
        Schema::dropIfExists('student_tuition');
        DB::setDefaultConnection('sqlite');
        DB::purge('hot_path_indexes_test');
    }
});

it('rebuilds an invalid index left by a failed concurrent build', function (): void {
    DB::table('student_tuition')->insert([
        ['enrollment_id' => 7],
        ['enrollment_id' => 7],
    ]);

    // Duplicate values make the unique build fail, which is the fastest
    // deterministic way to leave an invalid index under the expected name.
    try {
        DB::statement('CREATE UNIQUE INDEX CONCURRENTLY student_tuition_enrollment_id_index ON student_tuition (enrollment_id)');
    } catch (QueryException) {
        // Expected: the build itself is what produces the invalid index.
    }

    $invalid = DB::selectOne(
        <<<'SQL'
        SELECT 1 AS invalid
        FROM pg_index i
        JOIN pg_class c ON c.oid = i.indexrelid
        JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE c.relname = ?
          AND n.nspname = current_schema()
          AND NOT i.indisvalid
        SQL,
        ['student_tuition_enrollment_id_index'],
    );

    expect($invalid)->not->toBeNull();

    $migration = require database_path('migrations/2026_11_03_000001_add_hot_path_indexes.php');
    $migration->up();

    $valid = DB::selectOne(
        <<<'SQL'
        SELECT 1 AS valid
        FROM pg_index i
        JOIN pg_class c ON c.oid = i.indexrelid
        JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE c.relname = ?
          AND n.nspname = current_schema()
          AND i.indisvalid
        SQL,
        ['student_tuition_enrollment_id_index'],
    );

    $index = collect(Schema::getIndexes('student_tuition'))
        ->firstWhere('name', 'student_tuition_enrollment_id_index');

    expect($valid)->not->toBeNull()
        ->and($index)->not->toBeNull()
        ->and($index['unique'])->toBeFalse()
        ->and($index['columns'])->toBe(['enrollment_id']);
});
