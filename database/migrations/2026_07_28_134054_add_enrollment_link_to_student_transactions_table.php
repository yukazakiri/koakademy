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
        Schema::table('student_transactions', function (Blueprint $table): void {
            $table->foreignId('student_enrollment_id')
                ->nullable()
                ->after('student_id')
                ->constrained('student_enrollment')
                ->nullOnDelete();
            $table->string('idempotency_key', 96)->nullable()->after('status')->unique();
            $table->index(['student_enrollment_id', 'status'], 'student_transactions_enrollment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_transactions', function (Blueprint $table): void {
            $table->dropIndex('student_transactions_enrollment_status');
            $table->dropUnique(['idempotency_key']);
            $table->dropConstrainedForeignId('student_enrollment_id');
            $table->dropColumn('idempotency_key');
        });
    }
};
