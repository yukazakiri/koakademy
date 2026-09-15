<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_schedule_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->string('school_year', 32);
            $table->unsignedTinyInteger('semester');
            $table->unsignedTinyInteger('academic_year')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->decimal('lecture_rate_per_unit', 10, 2)->default(0);
            $table->decimal('laboratory_rate_per_unit', 10, 2)->default(0);
            $table->decimal('miscellaneous_fee', 10, 2)->default(0);
            $table->decimal('modular_fee', 10, 2)->default(2400.00);
            $table->decimal('nstp_multiplier', 4, 2)->default(0.50);
            $table->decimal('modular_lab_multiplier', 4, 2)->default(0.50);
            $table->json('rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['course_id', 'school_year', 'semester', 'academic_year', 'version'],
                'fee_schedule_versions_scope_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_schedule_versions');
    }
};
