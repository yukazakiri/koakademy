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
        Schema::table('class_enrollments', function (Blueprint $table): void {
            $table->boolean('is_finals_submitted')->nullable(true);
            $table->boolean('is_midterms_submitted')->nullable(true);
            $table->boolean('is_prelim_submitted')->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('class_enrollments', function (Blueprint $table): void {});
    }
};
