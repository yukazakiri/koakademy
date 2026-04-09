<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->foreignId('shs_strand_id')
                ->nullable()
                ->after('scholarship_details')
                ->constrained('shs_strands')
                ->nullOnDelete();

            $table->foreignId('shs_track_id')
                ->nullable()
                ->after('shs_strand_id')
                ->constrained('shs_tracks')
                ->nullOnDelete();

            $table->index('shs_strand_id', 'idx_students_shs_strand_id');
            $table->index('shs_track_id', 'idx_students_shs_track_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropForeign(['shs_strand_id']);
            $table->dropForeign(['shs_track_id']);
            $table->dropIndex('idx_students_shs_strand_id');
            $table->dropIndex('idx_students_shs_track_id');
            $table->dropColumn(['shs_strand_id', 'shs_track_id']);
        });
    }
};
