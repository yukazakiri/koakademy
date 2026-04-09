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
        if (! Schema::hasTable('admin_transactions')) {
            Schema::create('admin_transactions', function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->foreignId('admin_id')->constrained('users')->onDelete('cascade');
                $blueprint->foreignId('transaction_id')->constrained('transactions')->onDelete('cascade');
                $blueprint->decimal('amount', 10, 2);
                $blueprint->string('type'); // 'credit' or 'debit'
                $blueprint->text('description')->nullable();
                $blueprint->string('status')->default('pending');
                $blueprint->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_transactions');
    }
};
