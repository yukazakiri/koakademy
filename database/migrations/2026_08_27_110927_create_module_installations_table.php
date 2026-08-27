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
        Schema::create('module_installations', function (Blueprint $table): void {
            $table->id();
            $table->string('module_name')->unique();
            $table->string('composer_package')->nullable();
            $table->string('installed_version')->nullable();
            $table->boolean('enabled')->default(false);
            $table->boolean('restart_required')->default(false);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamps();

            $table->index(['enabled', 'restart_required']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_installations');
    }
};
