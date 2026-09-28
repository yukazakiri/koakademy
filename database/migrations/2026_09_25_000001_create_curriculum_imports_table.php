<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculum_imports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('uploaded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->string('filename');
            $table->string('checksum', 64);
            $table->string('status', 20)->default('review');
            $table->json('draft');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'uploaded_by_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_imports');
    }
};
