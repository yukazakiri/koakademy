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
        Schema::create('enrollment_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_enrollment_id')->constrained('student_enrollment')->cascadeOnDelete();
            $table->foreignId('enrollment_policy_snapshot_id')->nullable()->constrained()->nullOnDelete();
            $table->string('requirement_key');
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('enforcement_step_key')->nullable();
            $table->boolean('is_required')->default(true);
            $table->string('status', 20)->default('pending');
            $table->string('evidence_path')->nullable();
            $table->json('evidence')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('waived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('waived_at')->nullable();
            $table->text('waiver_reason')->nullable();
            $table->timestamps();

            $table->unique(['student_enrollment_id', 'requirement_key'], 'enrollment_requirement_unique');
            $table->index(['student_enrollment_id', 'enforcement_step_key', 'status'], 'enrollment_requirement_gate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollment_requirements');
    }
};
