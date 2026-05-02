<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Inventory\Enums\InventoryItemType;

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
            $table->string('item_type')->default(InventoryItemType::Tool->value);
            $table->boolean('is_borrowable')->default(true);
            $table->string('location_building')->nullable();
            $table->string('location_floor')->nullable();
            $table->string('location_area')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('wifi_ssid')->nullable();
            $table->text('wifi_password')->nullable();
            $table->string('login_username')->nullable();
            $table->text('login_password')->nullable();
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
            $table->dropColumn([
                'item_type',
                'is_borrowable',
                'location_building',
                'location_floor',
                'location_area',
                'ip_address',
                'wifi_ssid',
                'wifi_password',
                'login_username',
                'login_password',
            ]);
        });
    }
};
