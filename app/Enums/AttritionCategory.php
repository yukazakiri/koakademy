<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AttritionCategory: string implements HasColor, HasLabel
{
    case Academic = 'academic';
    case Financial = 'financial';
    case Personal = 'personal';
    case Transfer = 'transfer';
    case Relocation = 'relocation';
    case Other = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Academic => 'Academic Reasons',
            self::Financial => 'Financial Reasons',
            self::Personal => 'Personal Reasons',
            self::Transfer => 'Transfer to Another Institution',
            self::Relocation => 'Relocation',
            self::Other => 'Other Reasons',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Academic => Color::Red,
            self::Financial => Color::Orange,
            self::Personal => Color::Purple,
            self::Transfer => Color::Blue,
            self::Relocation => Color::Yellow,
            self::Other => Color::Gray,
        };
    }

    /**
     * Get the description of the attrition category
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::Academic => 'Failed courses, poor academic performance, or academic dismissal',
            self::Financial => 'Unable to afford tuition fees or other educational expenses',
            self::Personal => 'Family issues, health problems, or other personal circumstances',
            self::Transfer => 'Transferred to another educational institution',
            self::Relocation => 'Moved to a different location or area',
            self::Other => 'Other reasons not covered by the above categories',
        };
    }

    /**
     * Check if this category represents institutional failure
     */
    public function isInstitutionalIssue(): bool
    {
        return match ($this) {
            self::Academic, self::Financial => true,
            default => false,
        };
    }

    /**
     * Check if this category represents external factors
     */
    public function isExternalFactor(): bool
    {
        return match ($this) {
            self::Personal, self::Relocation => true,
            default => false,
        };
    }
}
