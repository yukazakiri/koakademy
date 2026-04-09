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
        if (! Schema::hasTable('students')) {
            Schema::create('students', function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->integer('institution_id')->nullable();
                $blueprint->integer('student_id')->nullable();
                $blueprint->string('lrn')->nullable();
                $blueprint->string('student_type')->nullable();
                $blueprint->string('first_name');
                $blueprint->string('middle_name')->nullable();
                $blueprint->string('last_name');
                $blueprint->string('suffix')->nullable();
                $blueprint->string('email')->nullable();
                $blueprint->string('phone')->nullable();
                $blueprint->date('birth_date');
                $blueprint->string('gender');
                $blueprint->string('civil_status')->nullable();
                $blueprint->string('nationality')->nullable();
                $blueprint->string('religion')->nullable();
                $blueprint->text('address')->nullable();
                $blueprint->string('emergency_contact')->nullable();
                $blueprint->string('status')->default('active');
                $blueprint->integer('age')->nullable();
                $blueprint->json('contacts')->nullable();
                $blueprint->foreignId('course_id')->nullable()->constrained('courses')->onDelete('set null');
                $blueprint->integer('academic_year')->nullable();
                $blueprint->text('remarks')->nullable();
                $blueprint->string('profile_url')->nullable();
                $blueprint->integer('student_contact_id')->nullable();
                $blueprint->integer('student_parent_info')->nullable();
                $blueprint->integer('student_education_id')->nullable();
                $blueprint->integer('student_personal_id')->nullable();
                $blueprint->integer('document_location_id')->nullable();
                $blueprint->string('clearance_status')->nullable();
                $blueprint->integer('year_graduated')->nullable();
                $blueprint->string('special_order')->nullable();
                $blueprint->date('issued_date')->nullable();
                $blueprint->timestamps();
                $blueprint->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
