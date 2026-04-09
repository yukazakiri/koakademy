<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_authors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('biography')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('nationality')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_authors');
    }
};
