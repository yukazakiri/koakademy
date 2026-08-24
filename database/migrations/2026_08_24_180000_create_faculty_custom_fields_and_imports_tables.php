<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faculty', function (Blueprint $table): void {
            $table->string('position')->nullable()->after('department');
            $table->date('date_employed')->nullable()->after('birth_date');
        });

        Schema::create('faculty_custom_field_definitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->string('key', 100);
            $table->string('label');
            $table->string('field_type', 20)->default('text');
            $table->text('help_text')->nullable();
            $table->json('options')->nullable();
            $table->json('source_header_aliases')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_sensitive')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['school_id', 'key']);
            $table->index(['school_id', 'is_active', 'display_order']);
        });

        Schema::create('faculty_custom_field_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignUuid('faculty_id')->constrained('faculty')->cascadeOnDelete();
            $table->foreignId('faculty_custom_field_definition_id')->constrained()->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['faculty_id', 'faculty_custom_field_definition_id'], 'faculty_custom_value_unique');
            $table->index(['school_id', 'faculty_custom_field_definition_id'], 'faculty_custom_value_school_definition_index');
        });

        Schema::create('faculty_bulk_imports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('uploaded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_filename');
            $table->string('source_type', 12);
            $table->string('checksum', 64);
            $table->string('status', 24)->default('review');
            $table->unsignedInteger('ready_count')->default(0);
            $table->unsignedInteger('invalid_count')->default(0);
            $table->unsignedInteger('applied_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status', 'created_at'], 'faculty_import_school_state_index');
        });

        Schema::create('faculty_bulk_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('faculty_bulk_import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained('schools')->restrictOnDelete();
            $table->unsignedInteger('row_number');
            $table->uuid('faculty_id')->nullable();
            $table->string('faculty_id_number')->nullable();
            $table->string('name')->nullable();
            $table->string('action', 12)->nullable();
            $table->text('payload')->nullable();
            $table->json('errors')->nullable();
            $table->json('warnings')->nullable();
            $table->json('result')->nullable();
            $table->string('status', 24)->default('invalid');
            $table->timestamps();

            $table->index(['school_id', 'faculty_bulk_import_id', 'status'], 'faculty_import_row_state_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faculty_bulk_import_rows');
        Schema::dropIfExists('faculty_bulk_imports');
        Schema::dropIfExists('faculty_custom_field_values');
        Schema::dropIfExists('faculty_custom_field_definitions');

        Schema::table('faculty', function (Blueprint $table): void {
            $table->dropColumn(['position', 'date_employed']);
        });
    }
};
