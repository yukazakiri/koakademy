<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StudentStatus: string implements HasColor, HasLabel
{
    case Applicant = 'applicant';
    case Enrolled = 'enrolled';
    case OnLeave = 'on_leave';
    case Withdrawn = 'withdrawn';
    case Dropped = 'dropped';
    case Graduated = 'graduated';
    case Transferred = 'transferred';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Applicant => 'Applicant',
            self::Enrolled => 'Enrolled',
            self::OnLeave => 'On Leave',
            self::Withdrawn => 'Withdrawn',
            self::Dropped => 'Dropped Out',
            self::Graduated => 'Graduated',
            self::Transferred => 'Transferred',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Applicant => Color::Yellow,
            self::Enrolled => Color::Green,
            self::OnLeave => Color::Orange,
            self::Withdrawn => Color::Red,
            self::Dropped => Color::Red,
            self::Graduated => Color::Blue,
            self::Transferred => Color::Purple,
        };
    }

    /**
     * Get the description of the student status
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::Applicant => 'Applied but not yet enrolled',
            self::Enrolled => 'Currently enrolled in the institution',
            self::OnLeave => 'On leave of absence',
            self::Withdrawn => 'Withdrew from studies',
            self::Dropped => 'Dropped out of the program',
            self::Graduated => 'Successfully completed the program',
            self::Transferred => 'Transferred to another institution',
        };
    }

    /**
     * Check if this status represents an active student
     */
    public function isActive(): bool
    {
        return match ($this) {
            self::Enrolled, self::OnLeave => true,
            default => false,
        };
    }

    /**
     * Check if this status represents a former student
     */
    public function isFormer(): bool
    {
        return match ($this) {
            self::Withdrawn, self::Dropped, self::Graduated, self::Transferred => true,
            default => false,
        };
    }

    /**
     * Check if this status represents attrition (dropout/withdrawal)
     */
    public function isAttrition(): bool
    {
        return match ($this) {
            self::Withdrawn, self::Dropped => true,
            default => false,
        };
    }
}
