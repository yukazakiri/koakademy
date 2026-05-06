<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            if (! Schema::hasColumn('students', 'income_bracket_mode')) {
                $table->string('income_bracket_mode')->default('annual')->after('family_income_bracket');
            }

            if (! Schema::hasColumn('students', 'use_same_parent_income')) {
                $table->boolean('use_same_parent_income')->default(true)->after('income_bracket_mode');
            }

            if (! Schema::hasColumn('students', 'father_income_bracket')) {
                $table->string('father_income_bracket')->nullable()->after('use_same_parent_income');
            }

            if (! Schema::hasColumn('students', 'mother_income_bracket')) {
                $table->string('mother_income_bracket')->nullable()->after('father_income_bracket');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $columnsToDrop = [
                'income_bracket_mode',
                'use_same_parent_income',
                'father_income_bracket',
                'mother_income_bracket',
            ];

            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('students', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
