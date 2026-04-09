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
        if (! Schema::hasTable('transactions')) {
            Schema::create('transactions', function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->text('description');
                $blueprint->string('status');
                $blueprint->datetime('transaction_date');
                $blueprint->string('transaction_number')->nullable();
                $blueprint->json('settlements')->nullable();
                $blueprint->string('invoicenumber')->nullable();
                $blueprint->text('signature')->nullable();
                $blueprint->string('transaction_type')->nullable();
                $blueprint->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
