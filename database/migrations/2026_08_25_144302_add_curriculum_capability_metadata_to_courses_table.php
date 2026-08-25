<?php

declare(strict_types=1);

use App\Models\SchoolCurriculumCapability;
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
            $table->foreignIdFor(SchoolCurriculumCapability::class)
                ->nullable()
                ->after('school_id')
                ->constrained()
                ->nullOnDelete();
            $table->string('curriculum_kind', 40)->default('legacy')->after('curriculum_year');
            $table->string('curriculum_stage', 50)->nullable()->after('curriculum_kind');
            $table->string('curriculum_framework', 50)->nullable()->after('curriculum_stage');
            $table->string('catalog_reference')->nullable()->after('curriculum_framework');
            $table->unsignedSmallInteger('duration_hours')->nullable()->after('catalog_reference');
            $table->string('qualification_level', 50)->nullable()->after('duration_hours');

            $table->index(['school_id', 'curriculum_kind']);
            $table->index(['school_curriculum_capability_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropIndex(['school_id', 'curriculum_kind']);
            $table->dropIndex(['school_curriculum_capability_id']);
            $table->dropConstrainedForeignIdFor(SchoolCurriculumCapability::class);
            $table->dropColumn([
                'curriculum_kind',
                'curriculum_stage',
                'curriculum_framework',
                'catalog_reference',
                'duration_hours',
                'qualification_level',
            ]);
        });
    }
};
