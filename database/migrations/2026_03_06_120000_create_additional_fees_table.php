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
        if (! Schema::hasTable('additional_fees')) {
            Schema::create('additional_fees', function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->foreignId('enrollment_id')->constrained('student_enrollment')->onDelete('cascade');
                $blueprint->string('fee_name');
                $blueprint->decimal('amount', 10, 2);
                $blueprint->boolean('is_separate_transaction')->default(false);
                $blueprint->string('transaction_number')->nullable();
                $blueprint->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('additional_fees');
    }
};
