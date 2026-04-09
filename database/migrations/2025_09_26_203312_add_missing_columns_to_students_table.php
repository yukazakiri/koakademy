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
        Schema::table('students', function (Blueprint $table): void {
            // Add missing columns that exist in the actual database
            if (! Schema::hasColumn('students', 'institution_id')) {
                $table->integer('institution_id')->nullable()->after('id');
            }

            if (! Schema::hasColumn('students', 'lrn')) {
                $table->string('lrn')->nullable()->after('student_id');
            }

            if (! Schema::hasColumn('students', 'student_type')) {
                $table->string('student_type')->nullable()->after('lrn');
            }

            if (! Schema::hasColumn('students', 'subject_enrolled')) {
                $table->json('subject_enrolled')->nullable()->after('issued_date');
            }

            if (! Schema::hasColumn('students', 'user_id')) {
                $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null')->after('subject_enrolled');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropColumn(['institution_id', 'lrn', 'student_type', 'subject_enrolled', 'user_id']);
        });
    }
};
