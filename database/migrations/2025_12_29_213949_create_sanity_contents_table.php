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
        Schema::create('sanity_contents', function (Blueprint $table): void {
            $table->id();
            $table->string('sanity_id')->unique();
            $table->string('document_type');
            $table->string('title');
            $table->text('content')->nullable();
            $table->json('meta_data')->nullable();
            $table->string('slug')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sanity_contents');
    }
};
