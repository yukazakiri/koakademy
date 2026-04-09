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
        if (! Schema::hasTable('classes')) {
            Schema::create('classes', function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->foreignId('subject_id')->nullable()->constrained('subject')->onDelete('cascade');
                $blueprint->string('subject_code');
                $blueprint->uuid('faculty_id')->nullable();
                $blueprint->integer('academic_year')->nullable();
                $blueprint->integer('semester');
                $blueprint->integer('schedule_id')->nullable();
                $blueprint->string('school_year');
                $blueprint->json('course_codes')->nullable();
                $blueprint->string('section');
                $blueprint->foreignId('room_id')->nullable()->constrained('rooms')->onDelete('set null');
                $blueprint->string('classification')->default('college');
                $blueprint->integer('maximum_slots')->nullable();
                $blueprint->integer('shs_track_id')->nullable();
                $blueprint->integer('shs_strand_id')->nullable();
                $blueprint->string('grade_level')->nullable();
                $blueprint->timestamps();

                $blueprint->foreign('faculty_id')->references('id')->on('faculty')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
