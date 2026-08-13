<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Regulatory curriculum frameworks supported by the setup wizard.
 *
 * Each framework is anchored to an official Philippine education authority
 * (CHED, DepEd, or TESDA) and carries the governing issuance as its reference.
 */
enum CurriculumFramework: string implements HasColor, HasLabel
{
    case ChedPsg = 'ched_psg';
    case DepedMatatag = 'deped_matatag';
    case DepedShsK12 = 'deped_shs_k12';
    case DepedShsRevised = 'deped_shs_revised';
    case TesdaTr = 'tesda_tr';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function optionsForFrontend(): array
    {
        return collect(self::cases())
            ->map(fn (self $framework): array => [
                'value' => $framework->value,
                'label' => $framework->getLabel(),
                'authority' => $framework->getAuthority(),
                'description' => $framework->getDescription(),
                'reference' => $framework->getReference(),
                'school_levels' => array_map(
                    fn (SchoolLevel $level): string => $level->value,
                    $framework->schoolLevels(),
                ),
                'source_url' => $framework->getSourceUrl(),
            ])
            ->values()
            ->all();
    }

    /**
     * The institution school levels where this framework applies.
     *
     * @return list<SchoolLevel>
     */
    public function schoolLevels(): array
    {
        return match ($this) {
            self::ChedPsg => [SchoolLevel::HigherEducation],
            self::DepedMatatag => [SchoolLevel::Elementary, SchoolLevel::JuniorHigh],
            self::DepedShsK12, self::DepedShsRevised => [SchoolLevel::SeniorHigh],
            self::TesdaTr => [SchoolLevel::HigherEducation, SchoolLevel::TechnicalVocational],
        };
    }

    public function getAuthority(): string
    {
        return match ($this) {
            self::ChedPsg => 'CHED',
            self::DepedMatatag, self::DepedShsK12, self::DepedShsRevised => 'DepEd',
            self::TesdaTr => 'TESDA',
        };
    }

    /**
     * The official issuance or policy this framework is based on.
     */
    public function getReference(): string
    {
        return match ($this) {
            self::ChedPsg => 'CMO 46 s. 2012 (OBE) + program PSGs',
            self::DepedMatatag => 'DepEd Order No. 010, s. 2024',
            self::DepedShsK12 => 'K to 12 SHS Curriculum (2013, updated)',
            self::DepedShsRevised => 'Revised SHS Curriculum (pilot SY 2025-2026)',
            self::TesdaTr => 'TESDA Training Regulations (TRs)',
        };
    }

    public function getSourceUrl(): string
    {
        return match ($this) {
            self::ChedPsg => 'https://ched.gov.ph/issuances/',
            self::DepedMatatag => 'https://www.deped.gov.ph/wp-content/uploads/DO_s2024_010.pdf',
            self::DepedShsK12, self::DepedShsRevised => 'https://www.deped.gov.ph/k-to-12/',
            self::TesdaTr => 'https://www.tesda.gov.ph/Download/Training_Regulations',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::ChedPsg => 'Outcomes-based degree programs following CHED Policies, Standards and Guidelines (PSGs).',
            self::DepedMatatag => 'Decongested K-10 curriculum with GMRC, Values Education, and Makabansa learning areas.',
            self::DepedShsK12 => 'Senior High School tracks and strands under the K to 12 program (grades 11-12).',
            self::DepedShsRevised => 'Two-track SHS pilot (Academic and Technical-Professional) with a 640-hour work immersion.',
            self::TesdaTr => 'Technical-vocational qualifications with National Certificate (NC I-IV) levels.',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::ChedPsg => 'CHED-Aligned Degree Programs',
            self::DepedMatatag => 'DepEd MATATAG Curriculum (K-10)',
            self::DepedShsK12 => 'DepEd Senior High School (K-12 Tracks)',
            self::DepedShsRevised => 'DepEd Revised SHS (Pilot)',
            self::TesdaTr => 'TESDA Technical-Vocational (NC I-IV)',
        };
    }

    public function getColor(): array
    {
        return match ($this) {
            self::ChedPsg => Color::Blue,
            self::DepedMatatag => Color::Green,
            self::DepedShsK12 => Color::Emerald,
            self::DepedShsRevised => Color::Amber,
            self::TesdaTr => Color::Violet,
        };
    }
}
