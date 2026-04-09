<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ScholarshipType: string implements HasColor, HasLabel
{
    case None = 'none';
    case TDP = 'tdp';
    case TES = 'tes';
    case Institutional = 'institutional';
    case Private = 'private';
    case Other = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::None => 'No Scholarship',
            self::TDP => 'TDP (Tulong Dunong Program)',
            self::TES => 'TES (Tertiary Education Subsidy)',
            self::Institutional => 'Institutional Scholarship',
            self::Private => 'Private/External Scholarship',
            self::Other => 'Other Scholarship',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::None => Color::Gray,
            self::TDP => Color::Blue,
            self::TES => Color::Green,
            self::Institutional => Color::Purple,
            self::Private => Color::Orange,
            self::Other => Color::Yellow,
        };
    }

    /**
     * Get the abbreviation for the scholarship type
     */
    public function getAbbreviation(): string
    {
        return match ($this) {
            self::None => 'N/A',
            self::TDP => 'TDP',
            self::TES => 'TES',
            self::Institutional => 'INST',
            self::Private => 'PRIV',
            self::Other => 'OTHER',
        };
    }

    /**
     * Get the description of the scholarship type
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::None => 'Student does not have any scholarship',
            self::TDP => 'CHED Tulong Dunong Program - merit-based scholarship',
            self::TES => 'CHED Tertiary Education Subsidy - need-based financial assistance',
            self::Institutional => 'Scholarship provided by the institution',
            self::Private => 'Scholarship from private organizations or individuals',
            self::Other => 'Other types of scholarship or financial assistance',
        };
    }

    /**
     * Check if this is a CHED scholarship
     */
    public function isChedScholarship(): bool
    {
        return match ($this) {
            self::TDP, self::TES => true,
            default => false,
        };
    }

    /**
     * Check if student has any scholarship
     */
    public function hasScholarship(): bool
    {
        return $this !== self::None;
    }
}
