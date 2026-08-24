<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrar_student_profile_imports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('uploaded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_filename');
            $table->string('checksum', 64);
            $table->unsignedSmallInteger('schema_version');
            $table->string('status', 24)->default('review');
            $table->unsignedInteger('ready_count')->default(0);
            $table->unsignedInteger('invalid_count')->default(0);
            $table->unsignedInteger('applied_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status', 'created_at'], 'registrar_profile_import_school_state_index');
        });

        Schema::create('registrar_student_profile_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('registrar_student_profile_import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->unsignedInteger('row_number');
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('student_enrollment_id')->nullable()->constrained('student_enrollment')->nullOnDelete();
            $table->string('student_number', 100)->nullable();
            $table->string('student_name')->nullable();
            $table->string('course_code', 100)->nullable();
            $table->unsignedSmallInteger('year_level')->nullable();
            $table->string('intake_category', 32)->nullable();
            $table->json('changes')->nullable();
            $table->json('errors')->nullable();
            $table->json('warnings')->nullable();
            $table->json('result')->nullable();
            $table->string('status', 24)->default('invalid');
            $table->timestamps();

            $table->unique(
                ['registrar_student_profile_import_id', 'student_id'],
                'registrar_profile_import_student_unique'
            );
            $table->index(
                ['school_id', 'registrar_student_profile_import_id', 'status'],
                'registrar_profile_import_row_state_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrar_student_profile_import_rows');
        Schema::dropIfExists('registrar_student_profile_imports');
    }
};
