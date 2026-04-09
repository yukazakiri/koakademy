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
        if (! Schema::hasTable('courses')) {
            Schema::create('courses', function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->string('code')->unique();
                $blueprint->string('title');
                $blueprint->text('description')->nullable();
                $blueprint->string('department')->nullable();
                $blueprint->integer('units')->default(0);
                $blueprint->string('lec_per_unit')->nullable();
                $blueprint->string('lab_per_unit')->nullable();
                $blueprint->integer('year_level')->default(1);
                $blueprint->integer('semester')->default(1);
                $blueprint->string('school_year')->nullable();
                $blueprint->string('curriculum_year')->nullable();
                $blueprint->string('miscellaneous')->nullable();
                $blueprint->string('miscelaneous')->nullable(); // Keep typo for compatibility
                $blueprint->text('remarks')->nullable();
                $blueprint->boolean('is_active')->default(true);
                $blueprint->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
