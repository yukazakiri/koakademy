<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('onboarding_features');
    }

    public function down(): void
    {
        Schema::create('onboarding_features', function (Blueprint $table): void {
            $table->id();
            $table->string('feature_key')->unique();
            $table->string('name');
            $table->enum('audience', ['student', 'faculty', 'all']);
            $table->text('summary')->nullable();
            $table->string('badge')->nullable();
            $table->string('accent')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->json('steps')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }
};
