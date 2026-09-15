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
            $table->decimal('gross_lecture', 12, 2)->nullable()->after('total_lectures');
            $table->foreignId('active_revision_id')->nullable()->after('assessment_adjustment')->constrained('assessment_revisions')->nullOnDelete();
            $table->boolean('needs_finance_review')->default(false)->after('active_revision_id');
        });
    }

    public function down(): void
    {
        Schema::table('student_tuition', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('active_revision_id');
            $table->dropColumn(['gross_lecture', 'needs_finance_review']);
        });
    }
};
