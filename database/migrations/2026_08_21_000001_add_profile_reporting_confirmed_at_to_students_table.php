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
            if (! Schema::hasColumn('students', 'profile_reporting_confirmed_at')) {
                $table->timestamp('profile_reporting_confirmed_at')->nullable()->after('marketing_consent_at');
            }
        });

        Schema::table('students_personal_info', function (Blueprint $table): void {
            if (! Schema::hasColumn('students_personal_info', 'current_adress')) {
                $table->text('current_adress')->nullable();
            }

            if (! Schema::hasColumn('students_personal_info', 'permanent_address')) {
                $table->text('permanent_address')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            if (Schema::hasColumn('students', 'profile_reporting_confirmed_at')) {
                $table->dropColumn('profile_reporting_confirmed_at');
            }
        });

        Schema::table('students_personal_info', function (Blueprint $table): void {
            $columns = array_filter([
                Schema::hasColumn('students_personal_info', 'current_adress') ? 'current_adress' : null,
                Schema::hasColumn('students_personal_info', 'permanent_address') ? 'permanent_address' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
