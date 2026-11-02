<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Existing installations may have custom-named, missing, or already-correct
     * foreign keys. Identify relationships by their columns, never by a guessed
     * constraint name. Keep this list in dependency-independent order.
     *
     * @var list<array{string, string, string, string}>
     */
    private const array RELATIONSHIPS = [
        ['student_tuition', 'enrollment_id', 'student_enrollment', 'restrict'],
        ['admin_transactions', 'admin_id', 'users', 'restrict'],
        ['admin_transactions', 'transaction_id', 'transactions', 'restrict'],
        ['student_transactions', 'transaction_id', 'transactions', 'restrict'],
        ['statement_of_account_issuances', 'student_id', 'students', 'restrict'],
        ['payment_allocations', 'transaction_id', 'transactions', 'restrict'],
        ['payment_allocations', 'student_id', 'students', 'restrict'],
        ['additional_fees', 'enrollment_id', 'student_enrollment', 'restrict'],
        ['assessment_revisions', 'student_tuition_id', 'student_tuition', 'restrict'],
        ['assessment_revisions', 'student_enrollment_id', 'student_enrollment', 'restrict'],
        ['student_tuition_update_requests', 'submitted_by_user_id', 'users', 'restrict'],
        ['student_tuition_update_requests', 'student_id', 'students', 'restrict'],
        ['general_settings', 'school_id', 'schools', 'set null'],
    ];

    public function up(): void
    {
        foreach (self::RELATIONSHIPS as [$table, $column, $target, $onDelete]) {
            $this->replaceForeignKey($table, $column, $target, $onDelete);
        }
    }

    public function down(): void
    {
        // Retain the migration's original rollback behavior; older databases may
        // have had different delete actions before the migration was applied.
        foreach (array_reverse(self::RELATIONSHIPS) as [$table, $column, $target]) {
            $this->replaceForeignKey($table, $column, $target, 'cascade');
        }
    }

    private function replaceForeignKey(string $table, string $column, string $target, string $onDelete): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (! Schema::hasColumn($table, $column) || ! Schema::hasTable($target) || ! Schema::hasColumn($target, 'id')) {
            throw new RuntimeException("Cannot protect {$table}.{$column}: the column or referenced table {$target}.id is missing. No financial records were removed.");
        }

        $foreignKeys = collect(Schema::getForeignKeys($table))
            ->filter(fn (array $foreignKey): bool => in_array($column, $foreignKey['columns'], true));
        $conflicting = $foreignKeys->first(fn (array $foreignKey): bool => $foreignKey['columns'] !== [$column]
            || $foreignKey['foreign_table'] !== $target
            || $foreignKey['foreign_columns'] !== ['id']);
        if ($conflicting !== null) {
            throw new RuntimeException("Cannot safely replace {$table}.{$column}: an existing foreign key references a different relationship or uses multiple columns. Inspect the database schema before retrying.");
        }

        $expectedSchema = DB::getDriverName() === 'pgsql' ? DB::selectOne('select current_schema() as name')->name : null;
        if ($expectedSchema !== null && $foreignKeys->contains(fn (array $foreignKey): bool => $foreignKey['foreign_schema'] !== $expectedSchema)) {
            throw new RuntimeException("Cannot safely replace {$table}.{$column}: the existing foreign key points to a different PostgreSQL schema. Inspect the database schema before retrying.");
        }

        if ($foreignKeys->count() === 1 && mb_strtolower((string) $foreignKeys->first()['on_delete']) === $onDelete) {
            return;
        }

        $updateActions = $foreignKeys->pluck('on_update')->map(fn ($action): string => mb_strtolower($action ?? 'no action'))->unique();
        if ($updateActions->count() > 1) {
            throw new RuntimeException("Cannot safely replace {$table}.{$column}: existing foreign keys have conflicting update actions.");
        }
        $onUpdate = $updateActions->first() ?? 'no action';

        // PostgreSQL and SQLite DDL participates in transactions. MySQL DDL
        // commits implicitly, so detect orphans before dropping a constraint.
        $orphans = DB::table($table)->whereNotNull($column)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from($target)->whereColumn($target.'.id', $table.'.'.$column))
            ->exists();
        if ($orphans && DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException("Cannot add protection for {$table}.{$column}: existing rows reference missing {$target} records. Back up and review the orphaned records before retrying; none were deleted.");
        }
        // PostgreSQL can enforce new and changed references while leaving
        // pre-existing orphans intact for an explicitly reviewed repair.
        $notValid = $orphans && DB::getDriverName() === 'pgsql';

        $change = function () use ($table, $column, $target, $onDelete, $onUpdate, $foreignKeys, $notValid): void {
            foreach ($foreignKeys as $foreignKey) {
                Schema::table($table, function (Blueprint $blueprint) use ($column, $foreignKey): void {
                    // SQLite exposes no names and rebuilds the table by column.
                    $blueprint->dropForeign($foreignKey['name'] ?? [$column]);
                });
            }
            Schema::table($table, function (Blueprint $blueprint) use ($column, $target, $onDelete, $onUpdate, $notValid): void {
                $constraint = $blueprint->foreign($column)->references('id')->on($target)->onUpdate($onUpdate)->onDelete($onDelete);
                if ($notValid) {
                    $constraint->notValid();
                }
            });
        };

        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::transaction($change);
        } else {
            $change();
        }
    }
};
