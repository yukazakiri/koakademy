<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ClearanceStatus: string implements HasColor, HasIcon, HasLabel
{
    case CLEARED = 'cleared';
    case NOT_CLEARED = 'not_cleared';
    case PENDING = 'pending';
    case CONDITIONAL = 'conditional';

    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn (self $status): ?string => $status->getLabel(), self::cases())
        );
    }

    public function getLabel(): ?string
    {
        return match ($this) {
            self::CLEARED => 'Cleared',
            self::NOT_CLEARED => 'Not Cleared',
            self::PENDING => 'Pending',
            self::CONDITIONAL => 'Conditional',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::CLEARED => 'success',
            self::NOT_CLEARED => 'danger',
            self::PENDING => 'warning',
            self::CONDITIONAL => 'info',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::CLEARED => 'heroicon-o-check-circle',
            self::NOT_CLEARED => 'heroicon-o-x-circle',
            self::PENDING => 'heroicon-o-clock',
            self::CONDITIONAL => 'heroicon-o-exclamation-circle',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CLEARED => 'Student has completed all clearance requirements',
            self::NOT_CLEARED => 'Student has not met clearance requirements',
            self::PENDING => 'Clearance is pending review',
            self::CONDITIONAL => 'Clearance granted with conditions',
        };
    }
}
