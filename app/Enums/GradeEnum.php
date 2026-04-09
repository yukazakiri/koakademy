<?php

declare(strict_types=1);

namespace App\Enums;

enum GradeEnum: string
{
    case OUTSTANDING = 'outstanding';
    case EXCELLENT = 'excellent';
    case SUPERIOR = 'superior';
    case VERY_GOOD = 'very_good';
    case GOOD = 'good';
    case SATISFACTORY = 'satisfactory';
    case FAIR = 'fair';
    case FAILED = 'failed';

    public static function fromGrade(float $grade): self
    {
        // Handle 1.00-4.00 scale
        if ($grade <= 4.00 && $grade >= 1.00) {
            return match (true) {
                $grade >= 4.00 => self::FAILED,
                $grade >= 3.00 => self::FAIR,
                $grade >= 2.50 => self::SATISFACTORY,
                $grade >= 2.00 => self::GOOD,
                $grade >= 1.75 => self::VERY_GOOD,
                $grade >= 1.50 => self::SUPERIOR,
                $grade >= 1.25 => self::EXCELLENT,
                $grade >= 1.00 => self::OUTSTANDING,
                default => self::FAILED,
            };
        }

        // Handle 60-100 scale
        return match (true) {
            $grade <= 74 => self::FAILED,
            $grade <= 79 => self::FAIR,
            $grade <= 84 => self::SATISFACTORY,
            $grade <= 87 => self::GOOD,
            $grade <= 90 => self::VERY_GOOD,
            $grade <= 93 => self::SUPERIOR,
            $grade <= 96 => self::EXCELLENT,
            $grade <= 100 => self::OUTSTANDING,
            default => self::FAILED,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::OUTSTANDING => 'Outstanding (1.00 / 97-100)',
            self::EXCELLENT => 'Excellent (1.25 / 94-96)',
            self::SUPERIOR => 'Superior (1.50 /  91-93)',
            self::VERY_GOOD => 'Very Good (1.75 / 88-90)',
            self::GOOD => 'Good (2.00 / 85-87)',
            self::SATISFACTORY => 'Satisfactory (2.50 / 80-84)',
            self::FAIR => 'Fair (3.00 / 75-79)',
            self::FAILED => 'Failed (4.00 / ≤74)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::OUTSTANDING => 'primary',
            self::EXCELLENT, self::SUPERIOR => 'info',
            self::VERY_GOOD, self::GOOD => 'success',
            self::SATISFACTORY, self::FAIR => 'warning',
            self::FAILED => 'danger',
        };
    }

    public function getGradeRanges(): array
    {
        return match ($this) {
            self::OUTSTANDING => ['point' => [1.00, 1.24], 'percentage' => [97, 100]],
            self::EXCELLENT => ['point' => [1.25, 1.49], 'percentage' => [94, 96]],
            self::SUPERIOR => ['point' => [1.50, 1.74], 'percentage' => [91, 93]],
            self::VERY_GOOD => ['point' => [1.75, 1.99], 'percentage' => [88, 90]],
            self::GOOD => ['point' => [2.00, 2.24], 'percentage' => [85, 87]],
            self::SATISFACTORY => ['point' => [2.25, 2.50], 'percentage' => [80, 84]],
            self::FAIR => ['point' => [2.75, 3.00], 'percentage' => [75, 79]],
            self::FAILED => ['point' => [4.00, 5.00], 'percentage' => [0, 74]],
        };
    }
}
