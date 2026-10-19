<?php

declare(strict_types=1);

namespace App\Enrollment;

use InvalidArgumentException;

/** Round each eligible component independently; unrelated charges are never discounted. */
final readonly class EnrollmentDiscountCalculation
{
    /** @return array{lecture: float, laboratory: float, amount: float} */
    public function calculate(float $lecture, float $laboratory, int $percentage, string $scope = 'lecture_only'): array
    {
        if (! in_array($scope, ['lecture_only', 'lecture_and_laboratory'], true) || $percentage < 0 || $percentage > 100) {
            throw new InvalidArgumentException('Unsupported enrollment discount scope or percentage.');
        }
        $lecture = round($lecture, 2);
        $laboratory = round($laboratory, 2);
        $lectureDiscount = round($lecture * $percentage / 100, 2);
        $labDiscount = $scope === 'lecture_and_laboratory' ? round($laboratory * $percentage / 100, 2) : 0.0;

        return [
            'lecture' => round($lecture - $lectureDiscount, 2),
            'laboratory' => round($laboratory - $labDiscount, 2),
            'amount' => round($lectureDiscount + $labDiscount, 2),
        ];
    }
}
