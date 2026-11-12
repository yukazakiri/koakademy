<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PostgreSQL-only coverage for the school scoped curriculum keys.
 *
 * The keys are built with CREATE UNIQUE INDEX CONCURRENTLY, which PostgreSQL
 * refuses to run inside a transaction block. The test therefore uses its own
 * connection, the same way the concurrent index test does, so the migration
 * runs outside the transaction the test suite wraps every test in.
 *
 * The dedicated test database must be empty and named test_*, matching the
 * other PostgreSQL migration tests.
 */
beforeEach(function (): void {
    $database = getenv('FINANCIAL_MIGRATION_PG_DATABASE');

    if (! is_string($database) || ! str_starts_with($database, 'test_')) {
        $this->markTestSkipped('Set FINANCIAL_MIGRATION_PG_* to a dedicated PostgreSQL test database named test_*.');
    }

    config(['database.connections.scope_codes_test' => [
        'driver' => 'pgsql', 'host' => getenv('FINANCIAL_MIGRATION_PG_HOST') ?: '127.0.0.1',
        'port' => getenv('FINANCIAL_MIGRATION_PG_PORT') ?: '5432', 'database' => $database,
        'username' => getenv('FINANCIAL_MIGRATION_PG_USERNAME') ?: 'postgres',
        'password' => getenv('FINANCIAL_MIGRATION_PG_PASSWORD') ?: '',
        'charset' => 'utf8', 'prefix' => '', 'schema' => 'public', 'sslmode' => 'prefer',
    ]]);

    DB::setDefaultConnection('scope_codes_test');

    if (Schema::getTableListing() !== []) {
        DB::setDefaultConnection('sqlite');

        throw new RuntimeException('The PostgreSQL migration test database must be empty; no existing tables were changed.');
    }

    Schema::create('schools', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('code');
        $table->timestamps();
    });

    Schema::create('courses', function (Blueprint $table): void {
        $table->id();
        $table->string('code');
        $table->string('title');
        $table->foreignId('school_id')->nullable();
        $table->string('curriculum_year')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });

    Schema::create('subject', function (Blueprint $table): void {
        $table->id();
        $table->string('classification');
        $table->string('code');
        $table->string('title');
        $table->integer('units');
        $table->integer('lecture');
        $table->integer('laboratory');
        $table->json('pre_riquisite')->nullable();
        $table->integer('semester');
        $table->foreignId('course_id');
        $table->boolean('is_credited')->default(true);
        $table->foreignId('school_id')->nullable();
        $table->timestamps();
    });

    $this->scopeCodesTablesCreated = true;
});

afterEach(function (): void {
    if (($this->scopeCodesTablesCreated ?? false) && DB::getDefaultConnection() === 'scope_codes_test') {
        Schema::dropIfExists('subject');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('schools');
        Schema::dropIfExists('migration_merge_conflicts');
        DB::setDefaultConnection('sqlite');
        DB::purge('scope_codes_test');
    }
});

/**
 * The duplicate curriculum production held.
 */
function seedDuplicatedPrograms(): void
{
    DB::table('schools')->insert([
        'id' => 4,
        'name' => 'KoAkademy Production Clone',
        'code' => 'KPCC',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('courses')->insert([
        [
            'id' => 14, 'code' => 'BSHRM (2009 - 2010)', 'title' => 'Bachelor of Science in Hotel and Restaurant Management',
            'school_id' => 4, 'curriculum_year' => '2009 - 2010', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ],
        [
            'id' => 15, 'code' => 'BSHRM (2009 - 2010)', 'title' => 'Bachelor of Science in Hotel and Restaurant Management',
            'school_id' => 4, 'curriculum_year' => '2009 - 2010', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ],
    ]);

    $subjects = [];

    foreach ([['NSTP 1', 14], ['NSTP 1', 15], ['HOS101', 14], ['HOS101', 15], ['HOS102', 15]] as [$code, $courseId]) {
        $subjects[] = [
            'classification' => 'internal',
            'code' => $code,
            'title' => $code,
            'units' => 3,
            'lecture' => 2,
            'laboratory' => 1,
            'semester' => 1,
            'course_id' => $courseId,
            'school_id' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    DB::table('subject')->insert($subjects);
}

it('merges the duplicated programs and builds the school scoped keys', function (): void {
    seedDuplicatedPrograms();

    $merge = require database_path('migrations/2026_11_08_000001_merge_duplicate_courses.php');
    $merge->up();

    $scope = require database_path('migrations/2026_11_09_000002_scope_course_and_subject_codes_to_school.php');
    $scope->up();

    expect(DB::table('courses')->where('code', 'BSHRM (2009 - 2010)')->count())->toBe(1)
        ->and(DB::table('subject')->where('course_id', 15)->count())->toBe(0)
        ->and(DB::table('subject')->where('course_id', 14)->count())->toBe(3);

    $courseIndex = collect(Schema::getIndexes('courses'))->firstWhere('name', 'courses_school_id_code_unique');
    $subjectIndex = collect(Schema::getIndexes('subject'))->firstWhere('name', 'subject_school_id_course_id_code_unique');

    expect($courseIndex)->not->toBeNull()
        ->and($courseIndex['unique'])->toBeTrue()
        ->and($courseIndex['columns'])->toBe(['school_id', 'code'])
        ->and($subjectIndex)->not->toBeNull()
        ->and($subjectIndex['unique'])->toBeTrue()
        ->and($subjectIndex['columns'])->toBe(['school_id', 'course_id', 'code']);

    // A concurrent build that failed leaves an invalid index behind. The
    // migration has to build a valid one.
    $invalid = DB::selectOne(
        <<<'SQL'
        SELECT 1 AS invalid
        FROM pg_index i
        JOIN pg_class c ON c.oid = i.indexrelid
        JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE c.relname IN ('courses_school_id_code_unique', 'subject_school_id_course_id_code_unique')
          AND n.nspname = current_schema()
          AND NOT i.indisvalid
        SQL,
    );

    expect($invalid)->toBeNull();
});

it('replaces a non-unique key that carries the expected name', function (): void {
    seedDuplicatedPrograms();

    $merge = require database_path('migrations/2026_11_08_000001_merge_duplicate_courses.php');
    $merge->up();

    // A non-unique key under the expected name does not enforce anything.
    // The migration has to notice and replace it.
    DB::statement('CREATE INDEX courses_school_id_code_unique ON courses (school_id, code)');

    $scope = require database_path('migrations/2026_11_09_000002_scope_course_and_subject_codes_to_school.php');
    $scope->up();

    $index = collect(Schema::getIndexes('courses'))->firstWhere('name', 'courses_school_id_code_unique');

    expect($index)->not->toBeNull()
        ->and($index['unique'])->toBeTrue()
        ->and($index['columns'])->toBe(['school_id', 'code']);
});

it('rejects a duplicate program code in the same school', function (): void {
    seedDuplicatedPrograms();

    $merge = require database_path('migrations/2026_11_08_000001_merge_duplicate_courses.php');
    $merge->up();

    $scope = require database_path('migrations/2026_11_09_000002_scope_course_and_subject_codes_to_school.php');
    $scope->up();

    expect(fn () => DB::table('courses')->insert([
        'code' => 'BSHRM (2009 - 2010)',
        'title' => 'Duplicate again',
        'school_id' => 4,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('allows the same subject code in another program of the same school', function (): void {
    seedDuplicatedPrograms();

    $merge = require database_path('migrations/2026_11_08_000001_merge_duplicate_courses.php');
    $merge->up();

    $scope = require database_path('migrations/2026_11_09_000002_scope_course_and_subject_codes_to_school.php');
    $scope->up();

    DB::table('courses')->insert([
        'id' => 16,
        'code' => 'BSIT (2024 - 2025)',
        'title' => 'Bachelor of Science in Information Technology',
        'school_id' => 4,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('subject')->insert([
        'classification' => 'internal',
        'code' => 'NSTP 1',
        'title' => 'NSTP 1',
        'units' => 3,
        'lecture' => 2,
        'laboratory' => 1,
        'semester' => 1,
        'course_id' => 16,
        'school_id' => 4,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('subject')->where('code', 'NSTP 1')->count())->toBe(2);

    expect(fn () => DB::table('subject')->insert([
        'classification' => 'internal',
        'code' => 'NSTP 1',
        'title' => 'NSTP 1 again',
        'units' => 3,
        'lecture' => 2,
        'laboratory' => 1,
        'semester' => 1,
        'course_id' => 14,
        'school_id' => 4,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
