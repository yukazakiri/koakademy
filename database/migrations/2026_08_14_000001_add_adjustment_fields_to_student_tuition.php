<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_tuition', function (Blueprint $table): void {
            $table->decimal('assessment_adjustment', 12, 2)->default(0)->after('overall_tuition');
            $table->decimal('paid_transaction_baseline', 12, 2)->nullable()->after('paid');
        });
    }

    public function down(): void
    {
        Schema::table('student_tuition', function (Blueprint $table): void {
            $table->dropColumn(['assessment_adjustment', 'paid_transaction_baseline']);
        });
    }
};
