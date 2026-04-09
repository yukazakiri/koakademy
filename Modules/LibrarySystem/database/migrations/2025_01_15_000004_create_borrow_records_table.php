<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_borrow_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained('library_books')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->datetime('borrowed_at');
            $table->datetime('due_date');
            $table->datetime('returned_at')->nullable();
            $table->enum('status', ['borrowed', 'returned', 'lost'])->default('borrowed');
            $table->decimal('fine_amount', 10, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['book_id', 'user_id']);
            $table->index(['status']);
            $table->index(['due_date']);
            $table->index(['borrowed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_borrow_records');
    }
};
