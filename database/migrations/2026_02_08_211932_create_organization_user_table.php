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
        Schema::create('organization_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('role')->nullable()->comment('User role within this organization');
            $table->boolean('is_primary')->default(false)->comment('Is this the user primary organization');
            $table->boolean('is_active')->default(true);
            $table->json('permissions')->nullable()->comment('Organization-specific permissions');
            $table->timestamps();

            // Unique constraint to prevent duplicate memberships
            $table->unique(['user_id', 'school_id']);

            // Index for efficient lookups
            $table->index(['school_id', 'is_active']);
            $table->index(['user_id', 'is_primary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_user');
    }
};
