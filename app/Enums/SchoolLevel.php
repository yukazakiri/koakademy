<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SchoolLevel: string implements HasColor, HasLabel
{
    case HigherEducation = 'higher_education';
    case JuniorHigh = 'junior_high';
    case SeniorHigh = 'senior_high';
    case Elementary = 'elementary';
    case TechnicalVocational = 'technical_vocational';

    /**
     * Get all school levels as array for forms.
     *
     * @return array<string, string>
     */
    public static function asSelectOptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $level): array => [$level->value => $level->getLabel()])
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public static function optionsForFrontend(): array
    {
        return collect(self::cases())
            ->map(fn (self $level): array => [
                'value' => $level->value,
                'label' => (string) $level->getLabel(),
                'description' => $level->getDescription(),
            ])
            ->values()
            ->all();
    }

    public function getLabel(): ?string
    {
        return match ($this) {
            self::HigherEducation => 'College / University',
            self::JuniorHigh => 'Middle School / Junior High School',
            self::SeniorHigh => 'Senior High School',
            self::Elementary => 'Elementary / Grade School',
            self::TechnicalVocational => 'TESDA / Technical-Vocational Institute',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::HigherEducation => 'Undergraduate, graduate, and university-level programs.',
            self::JuniorHigh => 'Middle school or junior high school operations.',
            self::SeniorHigh => 'Senior high school programs, usually grades 11 to 12.',
            self::Elementary => 'Elementary or grade school operations.',
            self::TechnicalVocational => 'Technical and vocational education and training (TVET) programs under TESDA regulations.',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::HigherEducation => Color::Blue,
            self::JuniorHigh => Color::Amber,
            self::SeniorHigh => Color::Green,
            self::Elementary => Color::Purple,
            self::TechnicalVocational => Color::Teal,
        };
    }
}
