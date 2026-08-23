<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->string('ched_major')->nullable();
            $table->boolean('ched_has_thesis')->nullable();
            $table->string('ched_program_status', 2)->nullable();
            $table->string('ched_authority_category', 2)->nullable();
            $table->string('ched_authority_serial')->nullable();
            $table->unsignedSmallInteger('ched_authority_year')->nullable();
            $table->string('ched_authority_other_program')->nullable();
            $table->string('ched_delivery_mode', 2)->nullable();
            $table->decimal('ched_normal_length_years', 4, 1)->nullable();
            $table->unsignedSmallInteger('ched_program_credit_units')->nullable();
            $table->decimal('ched_tuition_per_unit', 12, 2)->nullable();
            $table->decimal('ched_program_fee', 12, 2)->nullable();
        });

        Schema::table('student_enrollment', function (Blueprint $table): void {
            $table->string('intake_category', 32)->nullable();
            $table->index(['school_id', 'school_year', 'semester', 'course_id', 'academic_year', 'intake_category'], 'student_enrollment_form_bc_lookup');
        });

        Schema::table('students', function (Blueprint $table): void {
            $table->string('graduation_school_year', 20)->nullable();
            $table->unsignedTinyInteger('graduation_semester')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropColumn(['graduation_school_year', 'graduation_semester']);
        });

        Schema::table('student_enrollment', function (Blueprint $table): void {
            $table->dropIndex('student_enrollment_form_bc_lookup');
            $table->dropColumn('intake_category');
        });

        Schema::table('courses', function (Blueprint $table): void {
            $table->dropColumn([
                'ched_major', 'ched_has_thesis', 'ched_program_status', 'ched_authority_category',
                'ched_authority_serial', 'ched_authority_year', 'ched_authority_other_program',
                'ched_delivery_mode', 'ched_normal_length_years', 'ched_program_credit_units',
                'ched_tuition_per_unit', 'ched_program_fee',
            ]);
        });
    }
};
