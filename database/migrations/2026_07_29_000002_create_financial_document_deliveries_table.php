<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_document_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('financial_document_issuance_id')
                ->constrained('financial_document_issuances')
                ->cascadeOnDelete();
            $table->string('recipient');
            $table->string('status', 20)->default('queued')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('queued_at');
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_document_deliveries');
    }
};
