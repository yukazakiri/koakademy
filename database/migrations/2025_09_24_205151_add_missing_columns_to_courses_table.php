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
        Schema::table('courses', function (Blueprint $table): void {
            // Check if columns exist before adding them
            if (! Schema::hasColumn('courses', 'units')) {
                $table->integer('units')->default(0)->after('department');
            }

            if (! Schema::hasColumn('courses', 'year_level')) {
                $table->integer('year_level')->default(1)->after('lab_per_unit');
            }

            if (! Schema::hasColumn('courses', 'semester')) {
                $table->integer('semester')->default(1)->after('year_level');
            }

            if (! Schema::hasColumn('courses', 'school_year')) {
                $table->string('school_year')->nullable()->after('semester');
            }

            if (! Schema::hasColumn('courses', 'miscellaneous')) {
                $table->string('miscellaneous')->nullable()->after('curriculum_year');
            }

            if (! Schema::hasColumn('courses', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('remarks');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropColumn([
                'units',
                'year_level',
                'semester',
                'school_year',
                'miscellaneous',
                'is_active',
            ]);
        });
    }
};
