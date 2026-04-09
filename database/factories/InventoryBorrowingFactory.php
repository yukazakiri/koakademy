<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InventoryBorrowing;
use App\Models\InventoryProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryBorrowing>
 */
final class InventoryBorrowingFactory extends Factory
{
    protected $model = InventoryBorrowing::class;

    public function definition(): array
    {
        return [
            'product_id' => InventoryProduct::factory(),
            'quantity_borrowed' => $this->faker->numberBetween(1, 3),
            'borrower_name' => $this->faker->name(),
            'borrower_email' => $this->faker->safeEmail(),
            'borrower_phone' => $this->faker->phoneNumber(),
            'department' => $this->faker->randomElement(['IT', 'Maintenance', 'Facilities']),
            'purpose' => $this->faker->sentence(),
            'status' => 'borrowed',
            'borrowed_date' => now()->subDay(),
            'expected_return_date' => now()->addDays(5),
            'actual_return_date' => null,
            'quantity_returned' => 0,
            'return_notes' => null,
            'issued_by' => User::factory(),
            'returned_to' => null,
        ];
    }
}
