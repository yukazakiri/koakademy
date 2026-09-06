<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CurriculumFramework;
use App\Models\School;

final class RegulatoryReportRegistry
{
    public const string CHED_EFORM_BC = 'ched_eform_bc';

    public function definitions(): array
    {
        return [
            self::CHED_EFORM_BC => [
                'key' => self::CHED_EFORM_BC,
                'agency' => 'CHED',
                'country_code' => 'PH',
                'framework' => CurriculumFramework::ChedPsg->value,
            ],
        ];
    }

    public function context(?School $school): array
    {
        $availableDefinitions = $this->availableDefinitions($school);

        return [
            'country_code' => $school?->country_code,
            'available_report_keys' => array_keys($availableDefinitions),
            'agencies' => array_values(array_unique(array_column($availableDefinitions, 'agency'))),
            'definitions' => array_values($this->definitions()),
        ];
    }

    public function isAvailable(string $reportKey, ?School $school): bool
    {
        return array_key_exists($reportKey, $this->availableDefinitions($school));
    }

    private function availableDefinitions(?School $school): array
    {
        if (! $school instanceof School || $school->country_code !== 'PH') {
            return [];
        }

        return array_filter(
            $this->definitions(),
            fn (array $definition): bool => $definition['key'] === self::CHED_EFORM_BC
                && $this->hasChedCapability($school),
        );
    }

    private function hasChedCapability(School $school): bool
    {
        return $school->curriculumCapabilities()
            ->where('is_enabled', true)
            ->where('curriculum_framework', CurriculumFramework::ChedPsg->value)
            ->exists();
    }
}
