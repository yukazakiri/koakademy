<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('student_status_records') && ! Schema::hasColumn('student_status_records', 'school_id')) {
            Schema::table('student_status_records', function (Blueprint $table): void {
                $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
                $table->index('school_id');
            });

            DB::table('student_status_records')->whereNull('school_id')->update(['school_id' => 4]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('student_status_records', 'school_id')) {
            Schema::table('student_status_records', function (Blueprint $table): void {
                $table->dropForeign(['school_id']);
                $table->dropIndex(['school_id']);
                $table->dropColumn('school_id');
            });
        }
    }
};
