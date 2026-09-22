<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->foreignId('active_version_id')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('school_id');
        });

        Schema::create('grading_policy_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grading_policy_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('state', 20)->default('published');
            $table->json('configuration');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['grading_policy_id', 'version']);
            $table->index(['grading_policy_id', 'state']);
        });

        Schema::table('grading_policies', function (Blueprint $table): void {
            $table->foreign('active_version_id')->references('id')->on('grading_policy_versions')->nullOnDelete();
        });

        Schema::table('class_enrollments', function (Blueprint $table): void {
            $table->foreignId('grading_policy_version_id')->nullable()->after('total_average')->constrained()->nullOnDelete();
            $table->json('grading_components')->nullable()->after('grading_policy_version_id');
            $table->string('grade_symbol', 32)->nullable()->after('grading_components');
            $table->string('grade_outcome', 20)->nullable()->after('grade_symbol');
            $table->decimal('grade_quality_points', 10, 4)->nullable()->after('grade_outcome');
        });

        Schema::table('subject_enrollments', function (Blueprint $table): void {
            $table->foreignId('grading_policy_version_id')->nullable()->after('grade')->constrained()->nullOnDelete();
            $table->string('grade_symbol', 32)->nullable()->after('grading_policy_version_id');
            $table->string('grade_outcome', 20)->nullable()->after('grade_symbol');
            $table->decimal('grade_quality_points', 10, 4)->nullable()->after('grade_outcome');
        });
    }

    public function down(): void
    {
        Schema::table('subject_enrollments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('grading_policy_version_id');
            $table->dropColumn(['grade_symbol', 'grade_outcome', 'grade_quality_points']);
        });

        Schema::table('class_enrollments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('grading_policy_version_id');
            $table->dropColumn(['grading_components', 'grade_symbol', 'grade_outcome', 'grade_quality_points']);
        });

        Schema::table('grading_policies', function (Blueprint $table): void {
            $table->dropForeign(['active_version_id']);
        });

        Schema::dropIfExists('grading_policy_versions');
        Schema::dropIfExists('grading_policies');
    }
};
