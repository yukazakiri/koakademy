<?php

declare(strict_types=1);

use App\Enums\StudentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update existing 'active' status values to 'Enrolled' (case-insensitive)
        // This is a data migration to convert old string values to new enum values
        DB::table('students')
            ->whereRaw('LOWER(status) = ?', ['active'])
            ->update(['status' => StudentStatus::Enrolled->value]);

        // Set NULL status values to Enrolled as a sensible default
        DB::table('students')
            ->whereNull('status')
            ->update(['status' => StudentStatus::Enrolled->value]);

        // Set default values for new boolean fields if they are NULL
        DB::table('students')
            ->whereNull('is_indigenous_person')
            ->update(['is_indigenous_person' => false]);

        DB::table('students')
            ->whereNull('employed_by_institution')
            ->update(['employed_by_institution' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert Enrolled status back to 'active' for backward compatibility
        DB::table('students')
            ->where('status', StudentStatus::Enrolled->value)
            ->update(['status' => 'active']);
    }
};
