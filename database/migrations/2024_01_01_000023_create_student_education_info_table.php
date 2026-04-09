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
        if (! Schema::hasTable('student_education_info')) {
            Schema::create('student_education_info', function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->string('elementary_school')->nullable();
                $blueprint->string('elementary_year_graduated')->nullable();
                $blueprint->string('high_school')->nullable();
                $blueprint->string('high_school_year_graduated')->nullable();
                $blueprint->string('senior_high_school')->nullable();
                $blueprint->string('senior_high_year_graduated')->nullable();
                $blueprint->string('college_school')->nullable();
                $blueprint->string('college_course')->nullable();
                $blueprint->string('college_year_graduated')->nullable();
                $blueprint->string('vocational_school')->nullable();
                $blueprint->string('vocational_course')->nullable();
                $blueprint->string('vocational_year_graduated')->nullable();
                $blueprint->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_education_info');
    }
};
