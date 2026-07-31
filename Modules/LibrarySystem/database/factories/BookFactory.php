<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\LibrarySystem\Models\Author;
use Modules\LibrarySystem\Models\Book;
use Modules\LibrarySystem\Models\Category;

final class BookFactory extends Factory
{
    protected $model = Book::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'isbn' => $this->faker->optional()->isbn13(),
            'call_number' => $this->faker->optional()->bothify('??? ###'),
            'accession_number' => $this->faker->optional()->bothify('ACC-####'),
            'author_id' => Author::factory(),
            'category_id' => Category::factory(),
            'publisher' => $this->faker->optional()->company(),
            'publication_year' => $this->faker->optional()->numberBetween(1900, (int) date('Y')),
            'pages' => $this->faker->optional()->numberBetween(50, 1000),
            'description' => $this->faker->optional()->paragraph(),
            'cover_image' => $this->faker->optional()->imageUrl(),
            'cover_image_path' => null,
            'total_copies' => $this->faker->numberBetween(1, 10),
            'available_copies' => fn (array $attributes) => $attributes['total_copies'],
            'location' => $this->faker->optional()->word(),
            'status' => $this->faker->randomElement(['available', 'borrowed', 'maintenance']),
        ];
    }
}
