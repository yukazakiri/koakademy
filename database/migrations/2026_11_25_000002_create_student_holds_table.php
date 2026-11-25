<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_holds')) {
            return;
        }

        Schema::create('student_holds', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id')->nullable()->index();
            $table->unsignedBigInteger('student_id')->index();
            $table->string('office', 40)->default('registrar');
            $table->string('hold_type', 60)->default('general');
            $table->string('severity', 20)->default('blocking');
            $table->string('title', 160);
            $table->text('reason');
            $table->boolean('blocks_enrollment')->default(true);
            $table->boolean('blocks_add_drop')->default(false);
            $table->boolean('blocks_grade_release')->default(false);
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('placed_by')->nullable();
            $table->timestamp('placed_at')->useCurrent();
            $table->unsignedBigInteger('released_by')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->text('release_notes')->nullable();
            $table->unsignedBigInteger('overridden_by')->nullable();
            $table->timestamp('overridden_at')->nullable();
            $table->text('override_reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status'], 'student_holds_student_status_idx');
            $table->index(['school_id', 'status', 'office'], 'student_holds_school_status_office_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_holds');
    }
};
