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
        if (Schema::hasTable('student_id_change_logs')) {
            return;
        }

        Schema::create('student_id_change_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('old_student_id');
            $table->string('new_student_id');
            $table->string('student_name');
            $table->string('changed_by')->nullable();
            $table->json('affected_records')->nullable();
            $table->json('backup_data')->nullable();
            $table->boolean('is_undone')->default(false);
            $table->timestamp('undone_at')->nullable();
            $table->string('undone_by')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_id_change_logs');
    }
};
