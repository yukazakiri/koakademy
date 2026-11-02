<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $database = getenv('FINANCIAL_MIGRATION_PG_DATABASE');
    if (! is_string($database) || ! str_starts_with($database, 'test_')) {
        $this->markTestSkipped('Set FINANCIAL_MIGRATION_PG_* to a dedicated PostgreSQL test database named test_*.');
    }
    config(['database.connections.jsonb_migration_test' => [
        'driver' => 'pgsql', 'host' => getenv('FINANCIAL_MIGRATION_PG_HOST') ?: '127.0.0.1',
        'port' => getenv('FINANCIAL_MIGRATION_PG_PORT') ?: '5432', 'database' => $database,
        'username' => getenv('FINANCIAL_MIGRATION_PG_USERNAME') ?: 'postgres',
        'password' => getenv('FINANCIAL_MIGRATION_PG_PASSWORD') ?: '',
        'charset' => 'utf8', 'prefix' => '', 'schema' => 'public', 'sslmode' => 'prefer',
    ]]);
    DB::setDefaultConnection('jsonb_migration_test');
    if (Schema::getTableListing() !== []) {
        DB::setDefaultConnection('sqlite');
        throw new RuntimeException('The PostgreSQL migration test database must be empty; no existing tables were changed.');
    }
    DB::statement('CREATE TABLE classes (id bigserial primary key, course_codes text DEFAULT \'[101,202]\', subject_ids json DEFAULT \'[7]\'::json)');
    $this->jsonbTablesCreated = true;
    DB::statement('CREATE TABLE students (id bigserial primary key, school_id bigint, student_id integer, student_type text, created_at timestamp, updated_at timestamp, subject_enrolled text DEFAULT \'[9]\'::text, contacts text DEFAULT \'{"phone":"555"}\'::text)');
    DB::statement('CREATE TABLE class_enrollments (id bigserial primary key, prelim_grade numeric, midterm_grade numeric, finals_grade numeric, total_average numeric)');
    DB::statement('CREATE TABLE subject_enrollments (id bigserial primary key, grade numeric)');
});

afterEach(function (): void {
    if (($this->jsonbTablesCreated ?? false) && DB::getDefaultConnection() === 'jsonb_migration_test') {
        foreach (['student_configurations', 'id_sequences', 'faculty', 'general_settings', 'schools', 'subject_enrollments', 'class_enrollments', 'students', 'classes'] as $table) {
            Schema::dropIfExists($table);
        }
        DB::setDefaultConnection('sqlite');
        DB::purge('jsonb_migration_test');
    }
});

function migrateGradeJsonb(): void
{
    $migration = require database_path('migrations/2026_10_31_000001_add_grade_constraints_jsonb_indexes_and_admin_tx_status.php');
    DB::transaction(fn () => $migration->up());
}

it('converts all four text and JSON defaults to jsonb without changing their values', function (): void {
    DB::table('classes')->insert(['course_codes' => '[1,2]', 'subject_ids' => '[3]']);
    DB::table('students')->insert(['subject_enrolled' => '[4]', 'contacts' => '{"email":"student@example.test"}']);
    migrateGradeJsonb();
    foreach (['classes' => ['course_codes', 'subject_ids'], 'students' => ['subject_enrolled', 'contacts']] as $table => $columns) {
        foreach ($columns as $column) {
            $metadata = collect(Schema::getColumns($table))->firstWhere('name', $column);
            expect($metadata['type_name'])->toBe('jsonb')->and($metadata['default'])->not->toBeNull();
        }
    }
    expect(json_decode(DB::table('classes')->where('id', 1)->value('course_codes'), true))->toBe([1, 2])
        ->and(json_decode(DB::table('classes')->where('id', 1)->value('subject_ids'), true))->toBe([3])
        ->and(json_decode(DB::table('students')->where('id', 1)->value('subject_enrolled'), true))->toBe([4])
        ->and(json_decode(DB::table('students')->where('id', 1)->value('contacts'), true))->toBe(['email' => 'student@example.test']);
    $classId = DB::table('classes')->insertGetId([]);
    $studentId = DB::table('students')->insertGetId([]);
    expect(json_decode(DB::table('classes')->where('id', $classId)->value('course_codes'), true))->toBe([101, 202])
        ->and(json_decode(DB::table('classes')->where('id', $classId)->value('subject_ids'), true))->toBe([7])
        ->and(json_decode(DB::table('students')->where('id', $studentId)->value('subject_enrolled'), true))->toBe([9])
        ->and(json_decode(DB::table('students')->where('id', $studentId)->value('contacts'), true))->toBe(['phone' => '555']);
    expect(fn () => DB::table('class_enrollments')->insert(['prelim_grade' => 101]))->toThrow(QueryException::class);
    expect(collect(Schema::getIndexes('classes'))->pluck('name'))->toContain('classes_course_codes_gin_index', 'classes_subject_ids_gin_index');
});

it('converts blank and null historical values using the existing migration semantics', function (): void {
    DB::table('classes')->insert(['course_codes' => '   ', 'subject_ids' => null]);
    DB::table('students')->insert(['subject_enrolled' => '', 'contacts' => ' ']);
    migrateGradeJsonb();
    expect(json_decode(DB::table('classes')->where('id', 1)->value('course_codes'), true))->toBe([])
        ->and(json_decode(DB::table('classes')->where('id', 1)->value('subject_ids'), true))->toBe([])
        ->and(json_decode(DB::table('students')->where('id', 1)->value('subject_enrolled'), true))->toBe([])
        ->and(json_decode(DB::table('students')->where('id', 1)->value('contacts'), true))->toBe([]);
});

it('does not invent a default where a column previously had none', function (): void {
    DB::statement('ALTER TABLE classes ALTER COLUMN course_codes DROP DEFAULT');
    migrateGradeJsonb();
    expect(collect(Schema::getColumns('classes'))->firstWhere('name', 'course_codes')['default'])->toBeNull();
    $id = DB::table('classes')->insertGetId([]);
    expect(DB::table('classes')->where('id', $id)->value('course_codes'))->toBeNull();
});

it('keeps explicit SQL NULL defaults distinct from JSON null defaults', function (): void {
    DB::statement('ALTER TABLE classes ALTER COLUMN course_codes SET DEFAULT NULL');
    DB::statement("ALTER TABLE students ALTER COLUMN contacts SET DEFAULT 'null'::text");
    migrateGradeJsonb();
    $class = DB::table('classes')->insertGetId([]);
    $student = DB::table('students')->insertGetId([]);
    $values = DB::selectOne('SELECT (SELECT course_codes IS NULL FROM classes WHERE id = ?) AS sql_null, (SELECT jsonb_typeof(contacts) FROM students WHERE id = ?) AS json_type', [$class, $student]);
    expect($values->sql_null)->toBeTrue()->and($values->json_type)->toBe('null');
});

it('rolls back cleanly when the legacy default is not valid JSON', function (): void {
    DB::statement("ALTER TABLE classes ALTER COLUMN course_codes SET DEFAULT 'not-json'");
    expect(fn () => migrateGradeJsonb())->toThrow(QueryException::class);
    $column = collect(Schema::getColumns('classes'))->firstWhere('name', 'course_codes');
    expect($column['type_name'])->toBe('text')->and($column['default'])->toContain('not-json');
});

it('rolls back the original schema and defaults when historical JSON is invalid', function (): void {
    DB::table('classes')->insert(['course_codes' => 'not-json']);
    expect(fn () => migrateGradeJsonb())->toThrow(QueryException::class);
    $column = collect(Schema::getColumns('classes'))->firstWhere('name', 'course_codes');
    expect($column['type_name'])->toBe('text')->and($column['default'])->not->toBeNull()
        ->and(DB::table('classes')->where('id', 1)->value('course_codes'))->toBe('not-json');
});

it('runs the following student configuration and identifier migrations on PostgreSQL', function (): void {
    DB::statement('CREATE TABLE schools (id bigserial primary key)');
    DB::statement('CREATE TABLE faculty (id bigserial primary key, faculty_id_number text)');
    DB::statement('CREATE TABLE general_settings (id bigserial primary key, is_setup boolean NOT NULL DEFAULT false)');
    (require database_path('migrations/2026_07_05_104138_create_id_sequences_table.php'))->up();
    (require database_path('migrations/2026_10_05_120000_add_configuration_fields_to_id_sequences_table.php'))->up();
    DB::table('schools')->insert(['id' => 7]);
    DB::table('students')->insert(['id' => 9, 'school_id' => 7, 'student_id' => 208424, 'student_type' => 'college']);
    migrateGradeJsonb();
    (require database_path('migrations/2026_11_01_000001_create_student_configurations.php'))->up();
    (require database_path('migrations/2026_11_01_000002_scope_and_format_student_identifiers.php'))->up();
    expect(DB::table('students')->where('id', 9)->value('student_id'))->toBe('208424')
        ->and(DB::table('students')->where('id', 9)->value('identifier_key'))->toBe('school:7:208424')
        ->and(DB::table('student_configurations')->where('school_id', 7)->exists())->toBeTrue();
});
