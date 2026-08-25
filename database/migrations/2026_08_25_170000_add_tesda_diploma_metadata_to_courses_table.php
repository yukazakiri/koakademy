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
        Schema::table('courses', function (Blueprint $table): void {
            $table->string('tesda_program_type', 30)->nullable()->after('qualification_level');
            $table->decimal('duration_years', 3, 1)->nullable()->after('tesda_program_type');
            $table->unsignedSmallInteger('internship_hours')->nullable()->after('duration_years');
            $table->json('bundled_qualifications')->nullable()->after('internship_hours');
            $table->text('advanced_topics')->nullable()->after('bundled_qualifications');

            $table->index(['curriculum_kind', 'tesda_program_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropIndex(['curriculum_kind', 'tesda_program_type']);
            $table->dropColumn([
                'tesda_program_type',
                'duration_years',
                'internship_hours',
                'bundled_qualifications',
                'advanced_topics',
            ]);
        });
    }
};
