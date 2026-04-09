<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('isbn')->unique()->nullable();
            $table->foreignId('author_id')->constrained('library_authors')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('library_categories')->cascadeOnDelete();
            $table->string('publisher')->nullable();
            $table->integer('publication_year')->nullable();
            $table->integer('pages')->nullable();
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->integer('total_copies')->default(1);
            $table->integer('available_copies')->default(1);
            $table->string('location')->nullable();
            $table->enum('status', ['available', 'borrowed', 'maintenance'])->default('available');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['title', 'author_id']);
            $table->index(['isbn']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_books');
    }
};
