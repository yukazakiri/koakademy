<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\StudentTuition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StudentTuition>
 */
final class StudentTuitionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = StudentTuition::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalLectures = $this->faker->numberBetween(15000, 50000);
        $totalLaboratory = $this->faker->numberBetween(5000, 15000);
        $totalMiscellaneous = $this->faker->numberBetween(3000, 7000);
        $totalTuition = $totalLectures + $totalLaboratory;
        $overallTuition = $totalTuition + $totalMiscellaneous;
        $discount = $this->faker->numberBetween(0, 20);
        $discountAmount = $overallTuition * ($discount / 100);
        $overallTuitionAfterDiscount = $overallTuition - $discountAmount;
        $downpayment = $this->faker->numberBetween(0, $overallTuitionAfterDiscount * 0.5);
        $totalBalance = $overallTuitionAfterDiscount - $downpayment;

        return [
            'student_id' => $this->faker->numberBetween(100000, 999999),
            'semester' => $this->faker->randomElement(['1st Semester', '2nd Semester', 'Summer']),
            'academic_year' => $this->faker->numberBetween(2020, 2024),
            'total_lectures' => $totalLectures,
            'total_laboratory' => $totalLaboratory,
            'total_miscelaneous_fees' => $totalMiscellaneous,
            'total_tuition' => $totalTuition,
            'overall_tuition' => $overallTuitionAfterDiscount,
            'downpayment' => $downpayment,
            'discount' => $discount,
            'total_balance' => $totalBalance,
            'status' => $totalBalance <= 0 ? 'paid' : $this->faker->randomElement(['pending', 'partial', 'overdue']),
        ];
    }

    /**
     * Create a fully paid tuition
     */
    public function paid(): static
    {
        return $this->state(function (array $attributes): array {
            $overallTuition = $attributes['overall_tuition'];

            return [
                'downpayment' => $overallTuition,
                'total_balance' => 0,
                'status' => 'paid',
            ];
        });
    }

    /**
     * Create a tuition with outstanding balance
     */
    public function withBalance(): static
    {
        return $this->state(function (array $attributes): array {
            $overallTuition = $attributes['overall_tuition'];
            $downpayment = $overallTuition * 0.3; // 30% payment

            return [
                'downpayment' => $downpayment,
                'total_balance' => $overallTuition - $downpayment,
                'status' => 'pending',
            ];
        });
    }
}
