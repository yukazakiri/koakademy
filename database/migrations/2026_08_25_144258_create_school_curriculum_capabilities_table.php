<?php

declare(strict_types=1);

use App\Models\School;
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
        Schema::create('school_curriculum_capabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(School::class)->constrained()->cascadeOnDelete();
            $table->string('school_level', 50);
            $table->string('curriculum_framework', 50);
            $table->string('curriculum_reference')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'school_level', 'curriculum_framework'], 'school_curriculum_capability_unique');
            $table->index(['school_id', 'is_enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_curriculum_capabilities');
    }
};
