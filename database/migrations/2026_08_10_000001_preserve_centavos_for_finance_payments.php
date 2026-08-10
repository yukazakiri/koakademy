<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_transactions') && Schema::hasColumn('student_transactions', 'amount')) {
            Schema::table('student_transactions', function (Blueprint $table): void {
                $table->decimal('amount', 12, 2)->change();
            });
        }

        if (Schema::hasTable('student_tuition') && Schema::hasColumn('student_tuition', 'paid')) {
            Schema::table('student_tuition', function (Blueprint $table): void {
                $table->decimal('paid', 12, 2)->default(0)->change();
            });
        }

        if (! Schema::hasTable('inventory_stock_movements')) {
            Schema::create('inventory_stock_movements', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('product_id')->constrained('inventory_products')->cascadeOnDelete();
                $table->string('type');
                $table->unsignedInteger('quantity');
                $table->unsignedInteger('previous_stock');
                $table->unsignedInteger('new_stock');
                $table->string('reference')->nullable();
                $table->text('reason')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('movement_date');
                $table->timestamps();
                $table->index(['product_id', 'movement_date']);
            });
        }
    }

    /**
     * This is intentionally a forward-only repair: converting decimal payments back
     * to integers would silently discard recorded centavos.
     */
    public function down(): void {}
};
