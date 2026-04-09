<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Gender: string implements HasColor, HasLabel
{
    case Male = 'male';
    case Female = 'female';
    case Other = 'other';

    public static function random(): self
    {
        return fake()->randomElement(self::cases());
    }

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Male => 'Male',
            self::Female => 'Female',
            self::Other => 'Other',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Male => Color::Blue,
            self::Female => Color::Pink,
            self::Other => Color::Gray,
        };
    }
}
