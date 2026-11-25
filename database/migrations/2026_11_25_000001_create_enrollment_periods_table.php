<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('enrollment_periods')) {
            return;
        }

        Schema::create('enrollment_periods', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id')->nullable();
            $table->string('school_year', 32);
            $table->unsignedTinyInteger('semester');
            $table->timestamp('regular_starts_at')->nullable();
            $table->timestamp('regular_ends_at')->nullable();
            $table->timestamp('late_starts_at')->nullable();
            $table->timestamp('late_ends_at')->nullable();
            $table->timestamp('add_drop_deadline_at')->nullable();
            $table->decimal('late_fee_amount', 10, 2)->default(0);
            $table->string('late_fee_label', 120)->default('Late Enrollment Fee');
            $table->boolean('auto_apply_late_fee')->default(false);
            $table->boolean('enforce_windows')->default(false);
            $table->boolean('is_manually_closed')->default(false);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['school_year', 'semester'], 'enrollment_periods_term_idx');
            $table->index(['school_id', 'school_year', 'semester'], 'enrollment_periods_school_term_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_periods');
    }
};
