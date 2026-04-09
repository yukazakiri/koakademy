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
        Schema::table('class_attendance_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('class_attendance_sessions', 'is_no_meeting')) {
                $table->boolean('is_no_meeting')->default(false)->after('is_locked');
            }

            if (! Schema::hasColumn('class_attendance_sessions', 'no_meeting_reason')) {
                $table->string('no_meeting_reason', 80)->nullable()->after('is_no_meeting');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('class_attendance_sessions', function (Blueprint $table): void {
            if (Schema::hasColumn('class_attendance_sessions', 'no_meeting_reason')) {
                $table->dropColumn('no_meeting_reason');
            }

            if (Schema::hasColumn('class_attendance_sessions', 'is_no_meeting')) {
                $table->dropColumn('is_no_meeting');
            }
        });
    }
};
