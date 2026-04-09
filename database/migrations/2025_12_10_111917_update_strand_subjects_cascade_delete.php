<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, fix orphaned strand_subjects records
        // Update old strand IDs to current valid strand IDs
        DB::statement('UPDATE strand_subjects SET strand_id = 30 WHERE strand_id = 14'); // ABM
        DB::statement('UPDATE strand_subjects SET strand_id = 31 WHERE strand_id = 15'); // HUMSS

        // Delete records with strand_id = 23 (appears to be test data)
        DB::statement('DELETE FROM strand_subjects WHERE strand_id = 23');

        // Now add the foreign key constraint with cascade delete
        Schema::table('strand_subjects', function (Blueprint $table): void {
            // Drop existing foreign key first to avoid collision
            $table->dropForeign(['strand_id']);

            $table->foreign('strand_id')
                ->references('id')
                ->on('shs_strands')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the cascade foreign key constraint
        Schema::table('strand_subjects', function (Blueprint $table): void {
            try {
                $table->dropForeign(['strand_id']);
            } catch (Exception) {
                // Constraint doesn't exist, which is fine
            }
        });

        // Restore old strand IDs (revert the updates)
        DB::statement('UPDATE strand_subjects SET strand_id = 14 WHERE strand_id = 30'); // ABM back to 14
        DB::statement('UPDATE strand_subjects SET strand_id = 15 WHERE strand_id = 31'); // HUMSS back to 15
    }
};
