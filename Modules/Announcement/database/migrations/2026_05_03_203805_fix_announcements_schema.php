<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This is a **fix migration** for the announcements table. It ensures all
     * columns expected by the Announcement model and service layer exist,
     * backfills missing data, and drops the legacy user_id column.
     *
     * DDL and DML are combined here intentionally because this migration must
     * run atomically on existing production databases where some columns may
     * already be present (from earlier partial or rolled-back migrations).
     *
     * Schema::hasColumn() calls are evaluated *outside* the Schema::table()
     * closures so the check is accurate even when multiple migrations run in
     * the same batch.
     */
    public function up(): void
    {
        if (! Schema::hasTable('announcements')) {
            return;
        }

        // ── 1. Evaluate column existence *outside* Schema::table closures ──
        $hasSlug = Schema::hasColumn('announcements', 'slug');
        $hasStatus = Schema::hasColumn('announcements', 'status');
        $hasIsGlobal = Schema::hasColumn('announcements', 'is_global');
        $hasIsActive = Schema::hasColumn('announcements', 'is_active');
        $hasStartsAt = Schema::hasColumn('announcements', 'starts_at');
        $hasEndsAt = Schema::hasColumn('announcements', 'ends_at');
        $hasCreatedBy = Schema::hasColumn('announcements', 'created_by');
        $hasClassId = Schema::hasColumn('announcements', 'class_id');
        $hasSchoolId = Schema::hasColumn('announcements', 'school_id');
        $hasAttachments = Schema::hasColumn('announcements', 'attachments');
        $hasUserId = Schema::hasColumn('announcements', 'user_id');

        // ── 2. Add missing columns ──
        Schema::table('announcements', function (Blueprint $table) use (
            $hasSlug,
            $hasStatus,
            $hasIsGlobal,
            $hasIsActive,
            $hasStartsAt,
            $hasEndsAt,
            $hasCreatedBy,
            $hasClassId,
            $hasSchoolId,
            $hasAttachments,
        ): void {
            if (! $hasSlug) {
                $table->string('slug')->nullable();
            }

            if (! $hasStatus) {
                $table->string('status')->default('draft');
            }

            if (! $hasIsGlobal) {
                $table->boolean('is_global')->default(true);
            }

            if (! $hasIsActive) {
                $table->boolean('is_active')->default(true);
            }

            if (! $hasStartsAt) {
                $table->timestamp('starts_at')->nullable();
            }

            if (! $hasEndsAt) {
                $table->timestamp('ends_at')->nullable();
            }

            if (! $hasCreatedBy) {
                $table->unsignedBigInteger('created_by')->nullable();
            }

            if (! $hasClassId) {
                $table->integer('class_id')->nullable();
            }

            if (! $hasSchoolId) {
                $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
                $table->index('school_id');
            }

            if (! $hasAttachments) {
                $table->json('attachments')->nullable();
            }
        });

        // ── 3. Backfill data for existing rows ──

        // Slug: generate from title
        if (! $hasSlug) {
            DB::table('announcements')
                ->whereNull('slug')
                ->orWhere('slug', '')
                ->select('id', 'title')
                ->lazyById()
                ->each(function (stdClass $row): void {
                    DB::table('announcements')
                        ->where('id', $row->id)
                        ->update([
                            'slug' => Str::slug($row->title) ?? 'announcement-'.$row->id,
                        ]);
                });
        }

        // Status: existing rows are considered published
        if (! $hasStatus) {
            DB::table('announcements')
                ->whereNull('status')
                ->orWhere('status', '')
                ->update(['status' => 'published']);
        }

        // created_by: migrate from legacy user_id column
        if (! $hasCreatedBy && $hasUserId) {
            DB::table('announcements')
                ->whereNull('created_by')
                ->whereNotNull('user_id')
                ->update(['created_by' => DB::raw('user_id')]);
        }

        // ── 4. Add foreign key on created_by if missing ──
        $hasCreatedByFk = $this->hasForeignKey('announcements', 'announcements_created_by_foreign');
        if (! $hasCreatedByFk && ! $hasCreatedBy) {
            Schema::table('announcements', function (Blueprint $table): void {
                $table->foreign('created_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        // ── 5. Drop legacy user_id column ──
        if ($hasUserId) {
            $this->safeDropForeignKey('announcements', 'user_id');
            Schema::table('announcements', function (Blueprint $table): void {
                $table->dropColumn('user_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * Drops all columns added by this migration. For production databases
     * that already ran earlier migrations, some columns may have existed
     * before this migration; the hasColumn guards handle that safely.
     */
    public function down(): void
    {
        if (! Schema::hasTable('announcements')) {
            return;
        }

        $hasCreatedBy = Schema::hasColumn('announcements', 'created_by');
        $hasSchoolId = Schema::hasColumn('announcements', 'school_id');

        // Drop foreign keys outside Schema::table to avoid cascading errors
        if ($hasCreatedBy) {
            $this->safeDropForeignKey('announcements', 'created_by');
        }

        if ($hasSchoolId) {
            $this->safeDropForeignKey('announcements', 'school_id');
        }

        // Drop columns
        Schema::table('announcements', function (Blueprint $table): void {
            foreach (['slug', 'status', 'is_global', 'is_active', 'starts_at', 'ends_at',
                'created_by', 'class_id', 'school_id', 'attachments'] as $column) {
                if (Schema::hasColumn('announcements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Check if a foreign key exists on a table.
     */
    private function hasForeignKey(string $table, string $constraintName): bool
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'pgsql') {
            $result = $connection->selectOne(
                'SELECT COUNT(*) as count FROM information_schema.table_constraints WHERE table_name = ? AND constraint_name = ?',
                [$table, $constraintName]
            );

            return (int) $result->count > 0;
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $result = $connection->selectOne(
                'SELECT COUNT(*) as count FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
                [$table, $constraintName]
            );

            return (int) $result->count > 0;
        }

        // SQLite doesn't enforce FKs in a queryable way; default to false
        return false;
    }

    /**
     * Safely drop a foreign key, suppressing errors if it doesn't exist.
     */
    private function safeDropForeignKey(string $table, string $column): void
    {
        try {
            Schema::table($table, function (Blueprint $table) use ($column): void {
                $table->dropForeign([$column]);
            });
        } catch (Exception $e) {
            $message = mb_strtolower($e->getMessage());
            // PostgreSQL: "does not exist", MySQL: "doesn't exist", SQLite: "no such index"
            if (str_contains($message, 'does not exist')
                || str_contains($message, "doesn't exist")
                || str_contains($message, 'no such index')
            ) {
                return;
            }

            throw $e;
        }
    }
};
