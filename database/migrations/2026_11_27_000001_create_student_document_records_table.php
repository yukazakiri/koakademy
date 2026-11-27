<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_document_records')) {
            return;
        }

        Schema::create('student_document_records', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id')->nullable()->index();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('document_key', 64);
            $table->string('status', 32)->default('missing')->index();
            $table->string('file_path', 500)->nullable();
            $table->string('disk', 32)->nullable();
            $table->string('file_name', 255)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('waiver_reason')->nullable();
            $table->text('notes')->nullable();
            $table->date('expires_at')->nullable();
            $table->json('history')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'document_key'], 'student_doc_records_student_key_unique');
            $table->index(['school_id', 'status'], 'student_doc_records_school_status_idx');
            $table->index(['document_key', 'status'], 'student_doc_records_key_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_document_records');
    }
};
