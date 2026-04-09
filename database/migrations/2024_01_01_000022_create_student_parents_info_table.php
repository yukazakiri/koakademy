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
        if (! Schema::hasTable('student_parents_info')) {
            Schema::create('student_parents_info', function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->string('father_name')->nullable();
                $blueprint->string('father_occupation')->nullable();
                $blueprint->string('father_contact')->nullable();
                $blueprint->string('father_email')->nullable();
                $blueprint->string('mother_name')->nullable();
                $blueprint->string('mother_occupation')->nullable();
                $blueprint->string('mother_contact')->nullable();
                $blueprint->string('mother_email')->nullable();
                $blueprint->string('guardian_name')->nullable();
                $blueprint->string('guardian_relationship')->nullable();
                $blueprint->string('guardian_contact')->nullable();
                $blueprint->string('guardian_email')->nullable();
                $blueprint->text('family_address')->nullable();
                $blueprint->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_parents_info');
    }
};
