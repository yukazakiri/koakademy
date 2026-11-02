<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function financialConstraintFor(string $table, string $column): array
{
    return collect(Schema::getForeignKeys($table))->first(fn (array $foreignKey): bool => $foreignKey['columns'] === [$column]);
}

function replaceTuitionConstraintForTest(?string $target, string $action = 'cascade'): void
{
    Schema::table('student_tuition', fn (Blueprint $table) => $table->dropForeign(['enrollment_id']));
    if ($target !== null) {
        Schema::table('student_tuition', fn (Blueprint $table) => $table->foreign('enrollment_id', 'custom_tuition_enrollment_fk')->references('id')->on($target)->onUpdate('cascade')->onDelete($action));
    }
}

it('installs a missing tuition foreign key and preserves financial rows', function (): void {
    $course = App\Models\Course::factory()->create();
    $student = App\Models\Student::factory()->create();
    $enrollment = App\Models\StudentEnrollment::query()->create([
        'student_id' => $student->id, 'course_id' => $course->id, 'semester' => 1,
        'academic_year' => 1, 'school_year' => '2026-2027', 'status' => 'enrolled',
    ]);
    $tuition = App\Models\StudentTuition::query()->create([
        'student_id' => $student->id, 'enrollment_id' => $enrollment->id,
        'semester' => 1, 'school_year' => '2026-2027', 'academic_year' => 1,
    ]);
    replaceTuitionConstraintForTest(null);
    $migration = require database_path('migrations/2026_10_26_000001_restrict_financial_and_audit_foreign_keys.php');
    $migration->up();
    $migration->up(); // Retry after a partially completed nontransactional migration.
    expect(financialConstraintFor('student_tuition', 'enrollment_id')['on_delete'])->toBe('restrict');
    expect(fn () => DB::table('student_enrollment')->where('id', $enrollment->id)->delete())->toThrow(QueryException::class);
    expect(DB::table('student_tuition')->where('id', $tuition->id)->value('enrollment_id'))->toBe($enrollment->id);
});

it('replaces a legacy tuition foreign key without losing its update action', function (): void {
    replaceTuitionConstraintForTest('student_enrollment');
    $migration = require database_path('migrations/2026_10_26_000001_restrict_financial_and_audit_foreign_keys.php');
    $migration->up();
    $constraint = financialConstraintFor('student_tuition', 'enrollment_id');
    expect($constraint['foreign_table'])->toBe('student_enrollment')
        ->and($constraint['on_delete'])->toBe('restrict')
        ->and($constraint['on_update'])->toBe('cascade');
});

it('refuses to replace an unexpected foreign key relationship', function (): void {
    replaceTuitionConstraintForTest('students');
    $migration = require database_path('migrations/2026_10_26_000001_restrict_financial_and_audit_foreign_keys.php');
    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'different relationship');
    expect(financialConstraintFor('student_tuition', 'enrollment_id')['foreign_table'])->toBe('students');
});

it('reports orphaned tuition rows instead of deleting them or silently skipping the constraint', function (): void {
    replaceTuitionConstraintForTest(null);
    DB::table('student_tuition')->insert([
        'student_id' => 123, 'enrollment_id' => 987654, 'semester' => 1,
        'school_year' => '2026-2027', 'academic_year' => 1,
    ]);
    $migration = require database_path('migrations/2026_10_26_000001_restrict_financial_and_audit_foreign_keys.php');
    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'existing rows reference missing');
    expect(DB::table('student_tuition')->where('enrollment_id', 987654)->count())->toBe(1);
});
