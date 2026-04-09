<?php

declare(strict_types=1);

namespace Modules\Announcement\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Announcement\Models\Announcement;

final class AnnouncementFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Announcement::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'content' => $this->faker->paragraph(),
            'type' => $this->faker->randomElement(['info', 'success', 'warning', 'danger']),
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(7),
            'created_by' => User::factory(),
        ];
    }
}
