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
        Schema::table('student_tuition', function (Blueprint $table): void {
            $table->text('adjustment_note')->nullable()->after('discount_id');
            $table->foreignId('adjusted_by_user_id')->nullable()->after('adjustment_note')->constrained('users')->nullOnDelete();
            $table->timestampTz('adjusted_at')->nullable()->after('adjusted_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_tuition', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('adjusted_by_user_id');
            $table->dropColumn(['adjustment_note', 'adjusted_at']);
        });
    }
};
