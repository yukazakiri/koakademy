<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Nationality: string implements HasColor, HasLabel
{
    case Filipino = 'filipino';
    case American = 'american';
    case Chinese = 'chinese';
    case Korean = 'korean';
    case Japanese = 'japanese';
    case Indian = 'indian';
    case Indonesian = 'indonesian';
    case Malaysian = 'malaysian';
    case Vietnamese = 'vietnamese';
    case Thai = 'thai';
    case Singaporean = 'singaporean';
    case Taiwanese = 'taiwanese';
    case MiddleEastern = 'middle_eastern';
    case European = 'european';
    case Canadian = 'canadian';
    case Australian = 'australian';
    case Other = 'other';

    public static function random(): self
    {
        return fake()->randomElement(self::cases());
    }

    public static function forPhilippines(): self
    {
        return fake()->randomElement([
            self::Filipino,
            self::Filipino,
            self::Filipino,
            self::Filipino,
            self::Filipino,
            self::Filipino,
            self::Filipino,
            self::Filipino,
            self::Filipino,
            self::Korean,
            self::Chinese,
            self::American,
            self::Japanese,
            self::Indian,
        ]);
    }

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Filipino => 'Filipino',
            self::American => 'American',
            self::Chinese => 'Chinese',
            self::Korean => 'Korean',
            self::Japanese => 'Japanese',
            self::Indian => 'Indian',
            self::Indonesian => 'Indonesian',
            self::Malaysian => 'Malaysian',
            self::Vietnamese => 'Vietnamese',
            self::Thai => 'Thai',
            self::Singaporean => 'Singaporean',
            self::Taiwanese => 'Taiwanese',
            self::MiddleEastern => 'Middle Eastern',
            self::European => 'European',
            self::Canadian => 'Canadian',
            self::Australian => 'Australian',
            self::Other => 'Other',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Filipino => Color::Blue,
            self::American => Color::Gray,
            self::Chinese => Color::Red,
            self::Korean => Color::Blue,
            self::Japanese => Color::Red,
            self::Indian => Color::Orange,
            self::Indonesian => Color::Red,
            self::Malaysian => Color::Yellow,
            self::Vietnamese => Color::Red,
            self::Thai => Color::Red,
            self::Singaporean => Color::Red,
            self::Taiwanese => Color::Blue,
            self::MiddleEastern => Color::Green,
            self::European => Color::Blue,
            self::Canadian => Color::Red,
            self::Australian => Color::Blue,
            self::Other => Color::Gray,
        };
    }
}
