<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Account;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Account>
 */
final class AccountFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Account::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => $this->faker->optional(0.8)->dateTimeBetween('-1 year', 'now'),
            'password' => Hash::make('password'),
            'role' => $this->faker->randomElement(['admin', 'student', 'faculty', 'staff']),
            'person_id' => $this->faker->optional(0.7)->numberBetween(100000, 999999),
            'person_type' => $this->faker->optional(0.7)->randomElement([Student::class]),
            'is_active' => $this->faker->boolean(80), // 80% chance of being active
            'last_login' => $this->faker->optional(0.6)->dateTimeBetween('-1 month', 'now'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create a student account
     */
    public function student(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => 'student',
            'person_type' => Student::class,
        ]);
    }

    /**
     * Create an admin account
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => 'admin',
            'person_id' => null,
            'person_type' => null,
        ]);
    }

    /**
     * Create an active account
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => true,
            'email_verified_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    /**
     * Create an inactive account
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
            'last_login' => null,
        ]);
    }
}
