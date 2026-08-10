<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'students_cashier_identifier_idx';

    public function up(): void
    {
        if (Schema::hasTable('students') && ! Schema::hasIndex('students', self::INDEX)) {
            Schema::table('students', function (Blueprint $table): void {
                $table->index(['student_id', 'deleted_at'], self::INDEX);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('students') && Schema::hasIndex('students', self::INDEX)) {
            Schema::table('students', function (Blueprint $table): void {
                $table->dropIndex(self::INDEX);
            });
        }
    }
};
