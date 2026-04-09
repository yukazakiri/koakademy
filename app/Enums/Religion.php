<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Religion: string implements HasColor, HasLabel
{
    case Catholic = 'catholic';
    case Protestant = 'protestant';
    case IglesiaNiCristo = 'iglesia_ni_cristo';
    case Islam = 'islam';
    case Buddhist = 'buddhist';
    case Hindu = 'hindu';
    case Mormon = 'mormon';
    case JehovahWitness = 'jehovah_witness';
    case SeventhDayAdventist = 'seventh_day_adventist';
    case Baptist = 'baptist';
    case Evangelical = 'evangelical';
    case Pentecostal = 'pentecostal';
    case Agnostic = 'agnostic';
    case Atheist = 'atheist';
    case Other = 'other';

    public static function random(): self
    {
        return fake()->randomElement(self::cases());
    }

    public static function commonPhilippines(): self
    {
        return fake()->randomElement([
            self::Catholic,
            self::Catholic,
            self::Catholic,
            self::Catholic,
            self::Protestant,
            self::IglesiaNiCristo,
            self::Islam,
            self::Baptist,
            self::Evangelical,
        ]);
    }

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Catholic => 'Catholic',
            self::Protestant => 'Protestant',
            self::IglesiaNiCristo => 'Iglesia ni Cristo',
            self::Islam => 'Islam',
            self::Buddhist => 'Buddhist',
            self::Hindu => 'Hindu',
            self::Mormon => 'Mormon (LDS)',
            self::JehovahWitness => 'Jehovah\'s Witness',
            self::SeventhDayAdventist => 'Seventh Day Adventist',
            self::Baptist => 'Baptist',
            self::Evangelical => 'Evangelical',
            self::Pentecostal => 'Pentecostal',
            self::Agnostic => 'Agnostic',
            self::Atheist => 'Atheist',
            self::Other => 'Other',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Catholic => Color::Blue,
            self::Protestant => Color::Purple,
            self::IglesiaNiCristo => Color::Yellow,
            self::Islam => Color::Green,
            self::Buddhist => Color::Orange,
            self::Hindu => Color::Orange,
            self::Mormon => Color::Gray,
            self::JehovahWitness => Color::Gray,
            self::SeventhDayAdventist => Color::Gray,
            self::Baptist => Color::Blue,
            self::Evangelical => Color::Red,
            self::Pentecostal => Color::Red,
            self::Agnostic => Color::Gray,
            self::Atheist => Color::Gray,
            self::Other => Color::Gray,
        };
    }
}
