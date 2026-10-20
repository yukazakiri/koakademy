<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give the school configuration a soft delete column.
     *
     * The settings API exposes a delete endpoint, but the record it removes is
     * the school's global configuration. Without this column the delete was a
     * hard delete: the row was dropped from the table and the restore and
     * force-delete endpoints could never work because the model did not use
     * the SoftDeletes trait.
     *
     * Rows that were hard deleted before this migration are gone for good —
     * there is no data left to backfill — but every row that still exists now
     * survives a delete and can be restored.
     *
     * The migration is dated ahead of the older migrations that already read
     * the model (the soft delete scope filters on this column), so a fresh
     * installation publishes the column before they run.
     */
    public function up(): void
    {
        if (! Schema::hasTable('general_settings')) {
            return;
        }

        if (Schema::hasColumn('general_settings', 'deleted_at')) {
            return;
        }

        Schema::table('general_settings', function (Blueprint $table): void {
            $table->timestamp('deleted_at')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('general_settings')) {
            return;
        }

        if (! Schema::hasColumn('general_settings', 'deleted_at')) {
            return;
        }

        Schema::table('general_settings', function (Blueprint $table): void {
            $table->dropColumn('deleted_at');
        });
    }
};
