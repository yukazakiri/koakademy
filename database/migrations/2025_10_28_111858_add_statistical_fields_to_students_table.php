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
            // Demographics
            $table->string('ethnicity')->nullable()->after('nationality');
            $table->string('city_of_origin')->nullable()->after('ethnicity');
            $table->string('province_of_origin')->nullable()->after('city_of_origin');
            $table->string('region_of_origin')->nullable()->after('province_of_origin');

            // Indigenous Peoples tracking
            $table->boolean('is_indigenous_person')->default(false)->after('region_of_origin');
            $table->string('indigenous_group')->nullable()->after('is_indigenous_person');

            // Attrition tracking
            $table->date('withdrawal_date')->nullable()->after('indigenous_group');
            $table->text('withdrawal_reason')->nullable()->after('withdrawal_date');
            $table->string('attrition_category')->nullable()->after('withdrawal_reason');
            $table->date('dropout_date')->nullable()->after('attrition_category');

            // Employment tracking (for graduates)
            $table->string('employment_status')->nullable()->after('dropout_date');
            $table->string('employer_name')->nullable()->after('employment_status');
            $table->string('job_position')->nullable()->after('employer_name');
            $table->date('employment_date')->nullable()->after('job_position');
            $table->boolean('employed_by_institution')->default(false)->after('employment_date');

            // Scholarship tracking
            $table->string('scholarship_type')->nullable()->after('employed_by_institution');
            $table->text('scholarship_details')->nullable()->after('scholarship_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropColumn([
                'ethnicity',
                'city_of_origin',
                'province_of_origin',
                'region_of_origin',
                'is_indigenous_person',
                'indigenous_group',
                'withdrawal_date',
                'withdrawal_reason',
                'attrition_category',
                'dropout_date',
                'employment_status',
                'employer_name',
                'job_position',
                'employment_date',
                'employed_by_institution',
                'scholarship_type',
                'scholarship_details',
            ]);
        });
    }
};
