<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'announcements',
            'class_enrollments',
            'classes',
            'courses',
            'departments',
            'faculty',
            'rooms',
            'shs_students',
            'student_enrollment',
            'students',
            'subject',
            'subject_enrollments',
            'users',
        ];

        // Get the first school ID from the schools table
        $mainSchoolId = DB::table('schools')->first()?->id;

        // Check if school 4 exists to prevent foreign key errors
        $schoolExists = DB::table('schools')->where('id', $mainSchoolId)->exists();

        if ($schoolExists) {
            foreach ($tables as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'school_id')) {
                    DB::table($table)->whereNull('school_id')->update(['school_id' => $mainSchoolId]);
                }
            }

            // Handle organization_user mapping if needed for users who don't have it
            if (Schema::hasTable('users') && Schema::hasTable('organization_user')) {
                $userIds = DB::table('users')->pluck('id');
                foreach ($userIds as $userId) {
                    DB::table('organization_user')->updateOrInsert(
                        ['user_id' => $userId, 'school_id' => $mainSchoolId],
                        ['is_active' => true]
                    );
                }
            }
            // Backfill active_school_id in user_settings
            if (Schema::hasTable('user_settings') && Schema::hasColumn('user_settings', 'active_school_id')) {
                DB::table('user_settings')->whereNull('active_school_id')->update(['active_school_id' => $mainSchoolId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not reversible as we don't know which ones were previously null
    }
};
