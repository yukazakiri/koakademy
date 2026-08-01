<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_enrollment', function (Blueprint $table): void {
            $table->index(
                ['school_id', 'school_year', 'semester', 'status', 'deleted_at', 'course_id', 'academic_year'],
                'assessment_export_enrollment_lookup',
            );
        });
    }

    public function down(): void
    {
        Schema::table('student_enrollment', function (Blueprint $table): void {
            $table->dropIndex('assessment_export_enrollment_lookup');
        });
    }
};
