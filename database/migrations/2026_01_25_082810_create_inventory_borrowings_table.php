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
        if (Schema::hasTable('inventory_borrowings')) {
            return;
        }

        Schema::create('inventory_borrowings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->integer('quantity_borrowed');
            $table->string('borrower_name');
            $table->string('borrower_email')->nullable();
            $table->string('borrower_phone')->nullable();
            $table->string('department')->nullable();
            $table->text('purpose')->nullable();
            $table->string('status')->default('borrowed');
            $table->dateTime('borrowed_date');
            $table->dateTime('expected_return_date')->nullable();
            $table->dateTime('actual_return_date')->nullable();
            $table->integer('quantity_returned')->default(0);
            $table->text('return_notes')->nullable();
            $table->foreignId('issued_by')->constrained('users');
            $table->foreignId('returned_to')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_borrowings');
    }
};
