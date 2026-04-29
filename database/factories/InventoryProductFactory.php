<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InventoryItemType;
use App\Models\InventoryProduct;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InventoryProduct>
 */
final class InventoryProductFactory extends Factory
{
    protected $model = InventoryProduct::class;

    public function definition(): array
    {
        $sku = mb_strtoupper(Str::slug($this->faker->unique()->bothify('inv-###-??'), '-'));

        return [
            'name' => $this->faker->words(3, true),
            'sku' => $sku,
            'item_type' => InventoryItemType::Tool->value,
            'description' => $this->faker->sentence(),
            'category_id' => null,
            'supplier_id' => null,
            'price' => $this->faker->randomFloat(2, 0, 2500),
            'cost' => $this->faker->randomFloat(2, 0, 2000),
            'stock_quantity' => $this->faker->numberBetween(1, 25),
            'defective_quantity' => 0,
            'min_stock_level' => 1,
            'max_stock_level' => 50,
            'unit' => 'pcs',
            'barcode' => null,
            'track_stock' => true,
            'is_borrowable' => true,
            'is_active' => true,
            'images' => null,
            'notes' => null,
            'location_building' => null,
            'location_floor' => null,
            'location_area' => null,
            'ip_address' => null,
            'wifi_ssid' => null,
            'wifi_password' => null,
            'login_username' => null,
            'login_password' => null,
        ];
    }
}
