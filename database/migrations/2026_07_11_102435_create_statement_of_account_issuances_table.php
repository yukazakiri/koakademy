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
        Schema::create('statement_of_account_issuances', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained('student_enrollment')->nullOnDelete();
            $table->foreignId('tuition_id')->nullable()->constrained('student_tuition')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_number')->unique();
            $table->string('verification_token_hash', 64)->unique();
            $table->string('integrity_signature', 64);
            $table->json('snapshot');
            $table->string('status')->default('pending')->index();
            $table->string('disk')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('pdf_checksum', 64)->nullable();
            $table->timestampTz('issued_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statement_of_account_issuances');
    }
};
