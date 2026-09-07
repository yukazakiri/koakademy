<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            if (! Schema::hasColumn('schools', 'country_code')) {
                $table->char('country_code', 2)->nullable()->after('code');
            }
        });

        Schema::table('courses', function (Blueprint $table): void {
            if (! Schema::hasColumn('courses', 'ched_program_code')) {
                $table->string('ched_program_code', 50)->nullable();
            }

            if (! Schema::hasColumn('courses', 'ched_major_code')) {
                $table->string('ched_major_code', 50)->nullable();
            }

            if (! Schema::hasColumn('courses', 'ched_year_implemented')) {
                $table->unsignedSmallInteger('ched_year_implemented')->nullable();
            }
        });

        Schema::table('students', function (Blueprint $table): void {
            if (! Schema::hasColumn('students', 'is_solo_parent_dependent')) {
                $table->boolean('is_solo_parent_dependent')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            if (Schema::hasColumn('students', 'is_solo_parent_dependent')) {
                $table->dropColumn('is_solo_parent_dependent');
            }
        });

        Schema::table('courses', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('courses', 'ched_program_code') ? 'ched_program_code' : null,
                Schema::hasColumn('courses', 'ched_major_code') ? 'ched_major_code' : null,
                Schema::hasColumn('courses', 'ched_year_implemented') ? 'ched_year_implemented' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('schools', function (Blueprint $table): void {
            if (Schema::hasColumn('schools', 'country_code')) {
                $table->dropColumn('country_code');
            }
        });
    }
};
