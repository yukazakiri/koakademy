<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category');
            $table->string('mail_template')->nullable();
            $table->json('default_channels')->nullable();
            $table->json('variables')->nullable();
            $table->json('styles')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['slug', 'is_active']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
