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
        if (! Schema::hasTable('student_contacts')) {
            Schema::create('student_contacts', function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->string('personal_contact')->nullable();
                $blueprint->string('facebook')->nullable();
                $blueprint->string('twitter')->nullable();
                $blueprint->string('instagram')->nullable();
                $blueprint->string('linkedin')->nullable();
                $blueprint->string('emergency_contact_name')->nullable();
                $blueprint->string('emergency_contact_phone')->nullable();
                $blueprint->string('emergency_contact_relationship')->nullable();
                $blueprint->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_contacts');
    }
};
