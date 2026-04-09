<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\StudentTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StudentTransaction>
 */
final class StudentTransactionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = StudentTransaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = $this->faker->numberBetween(1000, 25000);

        return [
            'student_id' => $this->faker->numberBetween(100000, 999999),
            'transaction_type' => $this->faker->randomElement(['payment', 'fee', 'refund', 'adjustment']),
            'amount' => $amount,
            'description' => $this->faker->sentence(),
            'reference_number' => $this->faker->optional(0.8)->regexify('[A-Z]{2}[0-9]{8}'),
            'payment_method' => $this->faker->randomElement(['cash', 'check', 'bank_transfer', 'online']),
            'status' => $this->faker->randomElement(['completed', 'pending', 'cancelled']),
            'transaction_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'processed_by' => $this->faker->optional(0.9)->name(),
            'remarks' => $this->faker->optional(0.4)->sentence(),
        ];
    }

    /**
     * Create a payment transaction
     */
    public function payment(): static
    {
        return $this->state(fn (array $attributes): array => [
            'transaction_type' => 'payment',
            'status' => 'completed',
        ]);
    }

    /**
     * Create a fee transaction
     */
    public function fee(): static
    {
        return $this->state(fn (array $attributes): array => [
            'transaction_type' => 'fee',
            'status' => 'completed',
        ]);
    }

    /**
     * Create a pending transaction
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'pending',
            'processed_by' => null,
        ]);
    }

    /**
     * Create a recent transaction (within last 30 days)
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'transaction_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ]);
    }
}
