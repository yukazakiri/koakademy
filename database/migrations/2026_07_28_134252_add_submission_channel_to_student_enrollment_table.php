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
        Schema::table('student_enrollment', function (Blueprint $table): void {
            $table->string('submission_channel', 24)->default('administrator')->after('workflow_runtime')->index();
            $table->string('submission_idempotency_key', 64)
                ->nullable()
                ->after('submission_channel')
                ->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_enrollment', function (Blueprint $table): void {
            $table->dropIndex(['submission_channel']);
            $table->dropUnique(['submission_idempotency_key']);
            $table->dropColumn('submission_idempotency_key');
            $table->dropColumn('submission_channel');
        });
    }
};
