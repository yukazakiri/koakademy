<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CivilStatus: string implements HasColor, HasLabel
{
    case Single = 'single';
    case Married = 'married';
    case Widowed = 'widowed';
    case Separated = 'separated';
    case Annulled = 'annulled';
    case Cohabiting = 'cohabiting';

    public static function random(): self
    {
        return fake()->randomElement(self::cases());
    }

    public static function forStudents(): self
    {
        return fake()->randomElement([
            self::Single,
            self::Single,
            self::Single,
            self::Married,
        ]);
    }

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Single => 'Single',
            self::Married => 'Married',
            self::Widowed => 'Widowed',
            self::Separated => 'Separated',
            self::Annulled => 'Annulled',
            self::Cohabiting => 'Cohabiting',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Single => Color::Blue,
            self::Married => Color::Green,
            self::Widowed => Color::Gray,
            self::Separated => Color::Orange,
            self::Annulled => Color::Red,
            self::Cohabiting => Color::Purple,
        };
    }
}
