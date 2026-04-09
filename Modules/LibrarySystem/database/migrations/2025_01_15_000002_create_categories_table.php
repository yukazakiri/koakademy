<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#6366f1');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_categories');
    }
};
