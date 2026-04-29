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
        if (! Schema::hasTable('inventory_products')) {
            return;
        }

        Schema::table('inventory_products', function (Blueprint $table): void {
            $table->unsignedInteger('defective_quantity')->default(0)->after('stock_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('inventory_products')) {
            return;
        }

        Schema::table('inventory_products', function (Blueprint $table): void {
            $table->dropColumn('defective_quantity');
        });
    }
};
