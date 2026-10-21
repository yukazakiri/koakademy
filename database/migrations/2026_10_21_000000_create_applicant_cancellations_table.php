<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for applications that were closed by the registrar.
 *
 * The student record only carries the terminal status; this table keeps the
 * reason that was selected, the note the registrar wrote, the exact email that
 * was sent, and who made the call. A cancellation can be reverted, which keeps
 * the original row and marks it as reverted rather than deleting it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('applicant_cancellations')) {
            return;
        }

        Schema::create('applicant_cancellations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('reason_code', 64);
            $table->string('reason_label');
            $table->text('note')->nullable();
            $table->text('email_subject')->nullable();
            $table->text('email_body')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at');
            $table->foreignId('reverted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reverted_at')->nullable();
            $table->text('revert_reason')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'reverted_at'], 'idx_applicant_cancellations_active');
            $table->index(['student_id', 'cancelled_at'], 'idx_applicant_cancellations_student');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_cancellations');
    }
};
