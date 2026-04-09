<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EnrollStat: string implements HasColor, HasLabel
{
    case Pending = 'Pending';
    case VerifiedByDeptHead = 'Verified By Dept Head';
    case VerifiedByCashier = 'Verified By Cashier';

    /** Legacy value stored in older records — represents a fully enrolled student. */
    case Enrolled = 'enrolled';

    /**
     * Safe alternative to ::from() — returns null instead of throwing ValueError
     * for unknown/legacy backing values.
     */
    public static function tryFromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::VerifiedByDeptHead => 'Verified By Dept Head',
            self::VerifiedByCashier => 'Verified By Cashier',
            self::Enrolled => 'Enrolled',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending => Color::Yellow,
            self::VerifiedByDeptHead => Color::Green,
            self::VerifiedByCashier => Color::Blue,
            self::Enrolled => Color::Teal,
        };
    }
}
