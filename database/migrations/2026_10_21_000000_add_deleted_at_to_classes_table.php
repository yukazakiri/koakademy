<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give the classes table a soft delete column.
     *
     * Deleting a class used to be a hard delete: the row was dropped from the
     * table and the `deleting` hook only soft-deleted the schedules and the
     * enrolments, leaving orphaned rows pointing at a class that no longer
     * existed. There was no way back.
     *
     * With this column a delete archives the class and the records that depend
     * on it, and the restore endpoint brings all of them back. The model now
     * uses the SoftDeletes trait, so the column has to exist before any code
     * that reads the model runs.
     *
     * Rows that were hard deleted before this migration are gone for good —
     * there is no data left to backfill.
     */
    public function up(): void
    {
        if (! Schema::hasTable('classes')) {
            return;
        }

        if (Schema::hasColumn('classes', 'deleted_at')) {
            return;
        }

        Schema::table('classes', function (Blueprint $table): void {
            $table->timestamp('deleted_at')->nullable()->after('updated_at');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('classes')) {
            return;
        }

        if (! Schema::hasColumn('classes', 'deleted_at')) {
            return;
        }

        Schema::table('classes', function (Blueprint $table): void {
            $table->dropIndex(['deleted_at']);
            $table->dropColumn('deleted_at');
        });
    }
};
