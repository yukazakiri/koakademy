<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\LibrarySystem\Models\Author;

final class AuthorFactory extends Factory
{
    protected $model = Author::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'biography' => $this->faker->optional()->paragraph(),
            'birth_date' => $this->faker->optional()->date(),
            'nationality' => $this->faker->optional()->country(),
        ];
    }
}
