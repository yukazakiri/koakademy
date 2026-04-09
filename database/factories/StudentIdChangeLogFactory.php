<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\StudentIdChangeLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StudentIdChangeLog>
 */
final class StudentIdChangeLogFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = StudentIdChangeLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $oldId = $this->faker->numberBetween(100000, 899999);
        $newId = $this->faker->numberBetween(900000, 999999);

        return [
            'old_student_id' => (string) $oldId,
            'new_student_id' => (string) $newId,
            'student_name' => $this->faker->name(),
            'changed_by' => $this->faker->email(),
            'affected_records' => [
                'student_tuitions' => $this->faker->numberBetween(0, 5),
                'student_transactions' => $this->faker->numberBetween(0, 10),
                'student_enrollments' => $this->faker->numberBetween(0, 8),
                'class_enrollments' => $this->faker->numberBetween(0, 15),
                'subject_enrollments' => $this->faker->numberBetween(0, 20),
                'accounts' => $this->faker->numberBetween(0, 1),
                'total_updated' => $this->faker->numberBetween(1, 50),
            ],
            'backup_data' => [
                'student_data' => [
                    'id' => $oldId,
                    'first_name' => $this->faker->firstName(),
                    'last_name' => $this->faker->lastName(),
                    'email' => $this->faker->safeEmail(),
                ],
                'timestamp' => now()->toISOString(),
            ],
            'reason' => $this->faker->optional(0.8)->sentence(),
            'is_undone' => false,
            'undone_at' => null,
            'undone_by' => null,
        ];
    }

    /**
     * Create a change log that has been undone
     */
    public function undone(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_undone' => true,
            'undone_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'undone_by' => $this->faker->email(),
        ]);
    }

    /**
     * Create a recent change log
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'created_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Create a change log that can be undone
     */
    public function undoable(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_undone' => false,
            'undone_at' => null,
            'undone_by' => null,
            'created_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ]);
    }
}
