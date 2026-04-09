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
        Schema::table('subject_enrollments', function (Blueprint $blueprint): void {
            $blueprint->string('external_subject_code')->nullable()->after('school_name');
            $blueprint->string('external_subject_title')->nullable()->after('external_subject_code');
            $blueprint->integer('external_subject_units')->nullable()->after('external_subject_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subject_enrollments', function (Blueprint $blueprint): void {
            $blueprint->dropColumn([
                'external_subject_code',
                'external_subject_title',
                'external_subject_units',
            ]);
        });
    }
};
