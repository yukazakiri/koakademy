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
        // drop
        Schema::dropIfExists('class_post_attachments');
        Schema::dropIfExists('class_posts');
        // drop class_post_attachments
        Schema::create('class_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->string('title');
            $table->text('content')->nullable();
            $table->string('type');
            $table->json('attachments')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_posts');
    }
};
