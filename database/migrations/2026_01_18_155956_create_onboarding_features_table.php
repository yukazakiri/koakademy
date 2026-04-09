<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_features', function (Blueprint $table): void {
            $table->id();
            $table->string('feature_key', 100)->unique();
            $table->string('name');
            $table->string('audience');
            $table->text('summary')->nullable();
            $table->string('badge')->nullable();
            $table->string('accent')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->json('steps');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_features');
    }
};
