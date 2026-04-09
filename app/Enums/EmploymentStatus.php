<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EmploymentStatus: string implements HasColor, HasLabel
{
    case NotApplicable = 'not_applicable';
    case Unemployed = 'unemployed';
    case Employed = 'employed';
    case SelfEmployed = 'self_employed';
    case Underemployed = 'underemployed';
    case FurtherStudy = 'further_study';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::NotApplicable => 'Not Applicable',
            self::Unemployed => 'Unemployed',
            self::Employed => 'Employed',
            self::SelfEmployed => 'Self-Employed',
            self::Underemployed => 'Underemployed',
            self::FurtherStudy => 'Pursuing Further Study',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::NotApplicable => Color::Gray,
            self::Unemployed => Color::Red,
            self::Employed => Color::Green,
            self::SelfEmployed => Color::Blue,
            self::Underemployed => Color::Orange,
            self::FurtherStudy => Color::Purple,
        };
    }

    /**
     * Get the description of the employment status
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::NotApplicable => 'Employment status not applicable (e.g., non-graduate)',
            self::Unemployed => 'Actively seeking employment',
            self::Employed => 'Employed full-time or part-time',
            self::SelfEmployed => 'Self-employed or entrepreneur',
            self::Underemployed => 'Employed but not in field of study',
            self::FurtherStudy => 'Pursuing further education or training',
        };
    }

    /**
     * Check if this status represents employment
     */
    public function isEmployed(): bool
    {
        return match ($this) {
            self::Employed, self::SelfEmployed, self::Underemployed => true,
            default => false,
        };
    }

    /**
     * Check if this status represents unemployment
     */
    public function isUnemployed(): bool
    {
        return $this === self::Unemployed;
    }

    /**
     * Check if employment data is applicable
     */
    public function isApplicable(): bool
    {
        return $this !== self::NotApplicable;
    }
}
