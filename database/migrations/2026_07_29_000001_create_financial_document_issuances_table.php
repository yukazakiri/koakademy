<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_document_issuances', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 20)->index();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained('student_enrollment')->nullOnDelete();
            $table->foreignId('tuition_id')->nullable()->constrained('student_tuition')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_number')->unique();
            $table->string('paper_reference')->nullable()->index();
            $table->string('recipient')->nullable();
            $table->string('status', 30)->index();
            $table->json('snapshot');
            $table->string('integrity_signature', 64);
            $table->text('verification_token');
            $table->string('verification_token_hash', 64)->unique();
            $table->string('disk')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('pdf_checksum', 64)->nullable();
            $table->timestampTz('issued_at');
            $table->timestampTz('queued_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();

            $table->unique(['type', 'transaction_id']);
            $table->index(['type', 'enrollment_id', 'issued_at'], 'financial_docs_enrollment_type_issued_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_document_issuances');
    }
};
