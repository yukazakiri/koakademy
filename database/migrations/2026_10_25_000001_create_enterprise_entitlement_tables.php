<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enterprise_installations', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->uuid('uuid')->unique();
            $table->timestamp('created_at');
        });

        Schema::create('school_entitlements', function (Blueprint $table): void {
            $table->foreignId('school_id')->primary()->constrained()->cascadeOnDelete();
            $table->uuid('school_reference')->unique();
            $table->longText('signed_license')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });

        Schema::create('school_capabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('capability_key', 120);
            $table->boolean('enabled')->default(false);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['school_id', 'capability_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_capabilities');
        Schema::dropIfExists('school_entitlements');
        Schema::dropIfExists('enterprise_installations');
    }
};
