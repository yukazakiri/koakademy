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
        if (! Schema::hasTable('faculty')) {
            Schema::create('faculty', function (Blueprint $blueprint): void {
                $blueprint->uuid('id')->primary();
                $blueprint->string('faculty_id_number')->nullable();
                $blueprint->string('first_name');
                $blueprint->string('last_name');
                $blueprint->string('middle_name')->nullable();
                $blueprint->string('email')->unique();
                $blueprint->timestamp('email_verified_at')->nullable();
                $blueprint->string('password');
                $blueprint->string('phone_number')->nullable();
                $blueprint->string('department')->nullable();
                $blueprint->text('office_hours')->nullable();
                $blueprint->date('birth_date')->nullable();
                $blueprint->string('address_line1')->nullable();
                $blueprint->text('biography')->nullable();
                $blueprint->text('education')->nullable();
                $blueprint->text('courses_taught')->nullable();
                $blueprint->string('photo_url')->nullable();
                $blueprint->string('status')->default('active');
                $blueprint->string('gender')->nullable();
                $blueprint->integer('age')->nullable();
                $blueprint->rememberToken();
                $blueprint->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faculty');
    }
};
