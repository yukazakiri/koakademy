<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\LibrarySystem\Models\Category;

final class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'description' => $this->faker->optional()->sentence(),
            'color' => $this->faker->hexColor(),
        ];
    }
}
