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
        if (! Schema::hasTable('students_personal_info')) {
            Schema::create('students_personal_info', function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->string('place_of_birth')->nullable();
                $blueprint->string('citizenship')->nullable();
                $blueprint->string('blood_type')->nullable();
                $blueprint->decimal('height', 5, 2)->nullable();
                $blueprint->decimal('weight', 5, 2)->nullable();
                $blueprint->string('distinguishing_marks')->nullable();
                $blueprint->string('father_occupation')->nullable();
                $blueprint->string('mother_occupation')->nullable();
                $blueprint->text('hobbies')->nullable();
                $blueprint->text('special_skills')->nullable();
                $blueprint->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students_personal_info');
    }
};
