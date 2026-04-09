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
        if (! Schema::hasTable('subject')) {
            Schema::create('subject', function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->string('classification');
                $blueprint->string('code')->unique();
                $blueprint->string('title');
                $blueprint->integer('units');
                $blueprint->integer('lecture');
                $blueprint->integer('laboratory');
                $blueprint->json('pre_riquisite')->nullable();
                $blueprint->integer('academic_year')->nullable();
                $blueprint->integer('semester');
                $blueprint->foreignId('course_id')->constrained('courses')->onDelete('cascade');
                $blueprint->string('group')->nullable();
                $blueprint->boolean('is_credited')->default(true);
                $blueprint->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subject');
    }
};
