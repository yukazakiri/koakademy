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
        // 1. Optimize Dashboard Enrollment Stats
        // Current query: where "school_year" = ? and "semester" = ? and "status" = ?
        Schema::table('student_enrollment', function (Blueprint $table): void {
            $table->index(['school_year', 'semester', 'status', 'deleted_at'], 'idx_enrollment_stats');
        });

        // 2. Optimize Unread Notifications Count
        // Current query: where "notifiable_type" = ? and "notifiable_id" = ? and "read_at" is null
        Schema::table('notifications', function (Blueprint $table): void {
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'idx_notifications_unread');
        });

        // 3. Optimize Student Demographics & Filtering
        // Missing indexes identified from schema analysis
        Schema::table('students', function (Blueprint $table): void {
            $table->index(['status', 'deleted_at'], 'idx_students_status');
            $table->index(['student_type', 'deleted_at'], 'idx_students_type');
            $table->index(['academic_year', 'deleted_at'], 'idx_students_year');
            $table->index(['gender', 'deleted_at'], 'idx_students_gender');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_enrollment', function (Blueprint $table): void {
            $table->dropIndex('idx_enrollment_stats');
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex('idx_notifications_unread');
        });

        Schema::table('students', function (Blueprint $table): void {
            $table->dropIndex('idx_students_status');
            $table->dropIndex('idx_students_type');
            $table->dropIndex('idx_students_year');
            $table->dropIndex('idx_students_gender');
        });
    }
};
