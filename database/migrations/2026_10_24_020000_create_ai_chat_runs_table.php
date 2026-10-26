<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('school_id')->nullable()->index();
            $table->uuid('request_id');
            $table->uuid('conversation_id')->nullable()->index();
            $table->uuid('user_message_id')->nullable();
            $table->uuid('assistant_message_id')->nullable()->index();
            $table->string('agent', 64);
            $table->string('provider', 64)->nullable();
            $table->string('model')->nullable();
            $table->string('status', 24)->default('running');
            $table->json('options');
            $table->string('payload_hash', 64);
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'request_id']);
            $table->index(['user_id', 'conversation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_runs');
    }
};
