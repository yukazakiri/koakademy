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
        if (! Schema::hasTable('student_transactions')) {
            Schema::create('student_transactions', function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->integer('student_id');
                $blueprint->foreignId('transaction_id')->constrained('transactions')->onDelete('cascade');
                $blueprint->integer('amount');
                $blueprint->string('status')->nullable();
                $blueprint->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_transactions');
    }
};
