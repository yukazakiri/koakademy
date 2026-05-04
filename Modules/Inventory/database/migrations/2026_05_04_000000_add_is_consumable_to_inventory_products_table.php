<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_products')) {
            return;
        }

        Schema::table('inventory_products', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_products', 'is_consumable')) {
                $table->boolean('is_consumable')->default(false)->after('is_borrowable');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory_products')) {
            return;
        }

        Schema::table('inventory_products', function (Blueprint $table): void {
            if (Schema::hasColumn('inventory_products', 'is_consumable')) {
                $table->dropColumn('is_consumable');
            }
        });
    }
};
