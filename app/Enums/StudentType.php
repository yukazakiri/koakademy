<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StudentType: string implements HasColor, HasLabel
{
    case College = 'college';
    case SeniorHighSchool = 'shs';
    case TESDA = 'tesda';
    case DHRT = 'dhrt';

    /**
     * Get all student types as array for forms
     */
    public static function asSelectOptions(): array
    {
        return [
            self::College->value => self::College->getLabel(),
            self::SeniorHighSchool->value => self::SeniorHighSchool->getLabel(),
            self::TESDA->value => self::TESDA->getLabel(),
            self::DHRT->value => self::DHRT->getLabel(),
        ];
    }

    public function getLabel(): ?string
    {
        return match ($this) {
            self::College => 'College Student',
            self::SeniorHighSchool => 'Senior High School Student',
            self::TESDA => 'TESDA Student',
            self::DHRT => 'DHRT Student',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::College => Color::Blue,
            self::SeniorHighSchool => Color::Green,
            self::TESDA => Color::Orange,
            self::DHRT => Color::Purple,
        };
    }

    /**
     * Get the abbreviation for the student type
     */
    public function getAbbreviation(): string
    {
        return match ($this) {
            self::College => 'COL',
            self::SeniorHighSchool => 'SHS',
            self::TESDA => 'TES',
            self::DHRT => 'DHRT',
        };
    }

    /**
     * Get the description of the student type
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::College => 'College level students pursuing bachelor\'s degrees',
            self::SeniorHighSchool => 'Senior High School students in grades 11-12',
            self::TESDA => 'Technical Education and Skills Development Authority students',
            self::DHRT => 'DHRT students pursuing specialized technical programs',
        };
    }

    /**
     * Check if this student type requires LRN (Learner Reference Number)
     */
    public function requiresLrn(): bool
    {
        return match ($this) {
            self::SeniorHighSchool => true,
            self::College => false,
            self::TESDA => false,
            self::DHRT => false,
        };
    }

    /**
     * Get the student ID prefix for this type
     */
    public function getIdPrefix(): string
    {
        return match ($this) {
            self::College => '2', // College students start with 2
            self::SeniorHighSchool => '3', // SHS students start with 3
            self::TESDA => '2', // TESDA students start with 2 (changed from 4)
            self::DHRT => '2', // DHRT students start with 2
        };
    }
}
