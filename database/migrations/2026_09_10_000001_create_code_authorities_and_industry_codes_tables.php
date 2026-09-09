<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_authorities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->string('key', 100);
            $table->string('name');
            $table->string('country_code', 2)->nullable();
            $table->string('curriculum_framework', 50)->nullable();
            $table->text('description')->nullable();
            $table->json('schema')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'key']);
            $table->index(['school_id', 'is_active']);
        });

        Schema::create('industry_course_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('code_authority_id')->constrained('code_authorities')->cascadeOnDelete();
            $table->string('code', 100);
            $table->string('title', 500);
            $table->string('category_code', 50)->nullable();
            $table->string('category_name', 255)->nullable();
            $table->json('attributes')->nullable();
            $table->string('source', 12)->default('manual');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'code_authority_id', 'code'], 'industry_codes_school_authority_code_unique');
            $table->index(['school_id', 'code_authority_id', 'category_code'], 'industry_codes_school_authority_category_index');
            $table->index(['school_id', 'code_authority_id', 'is_active'], 'industry_codes_school_authority_state_index');
        });

        Schema::create('code_authority_imports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('code_authority_id')->constrained('code_authorities')->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_filename');
            $table->string('checksum', 64);
            $table->json('field_proposals')->nullable();
            $table->string('status', 24)->default('review');
            $table->unsignedInteger('ready_count')->default(0);
            $table->unsignedInteger('invalid_count')->default(0);
            $table->unsignedInteger('applied_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status', 'created_at'], 'code_import_school_state_index');
        });

        Schema::create('code_authority_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('code_authority_import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('code', 100)->nullable();
            $table->string('title', 500)->nullable();
            $table->string('category_code', 50)->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('action', 12)->nullable();
            $table->text('payload')->nullable();
            $table->json('errors')->nullable();
            $table->json('warnings')->nullable();
            $table->json('result')->nullable();
            $table->string('status', 24)->default('invalid');
            $table->timestamps();

            $table->index(['school_id', 'code_authority_import_id', 'status'], 'code_import_row_state_index');
        });

        Schema::table('courses', function (Blueprint $table): void {
            if (! Schema::hasColumn('courses', 'industry_course_code_id')) {
                $table->foreignId('industry_course_code_id')
                    ->nullable()
                    ->after('school_curriculum_capability_id')
                    ->constrained('industry_course_codes')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            if (Schema::hasColumn('courses', 'industry_course_code_id')) {
                $table->dropConstrainedForeignIdFor(App\Models\IndustryCourseCode::class);
            }
        });

        Schema::dropIfExists('code_authority_import_rows');
        Schema::dropIfExists('code_authority_imports');
        Schema::dropIfExists('industry_course_codes');
        Schema::dropIfExists('code_authorities');
    }
};
