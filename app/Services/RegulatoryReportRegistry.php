<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\RegulatoryReportAdapter;
use App\Models\School;
use InvalidArgumentException;

final class RegulatoryReportRegistry
{
    public const string CHED_EFORM_BC = 'ched_eform_bc';

    public const string CHED_BACCALAUREATE = 'ched_baccalaureate';

    public const string CHED_SPECIAL_EQUITY = 'ched_special_equity';

    public const string CHED_EQUITY_ENROLLMENT = 'ched_equity_enrollment';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        $definitions = config('regulatory-reports.definitions', []);

        if (! is_array($definitions)) {
            return [];
        }

        return array_filter(
            $definitions,
            static fn (mixed $definition): bool => is_array($definition)
                && ($definition['enabled'] ?? true) === true
                && is_string($definition['key'] ?? null)
                && is_string($definition['adapter'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function definition(string $reportKey): ?array
    {
        $definition = $this->definitions()[$reportKey] ?? null;

        return is_array($definition) ? $definition : null;
    }

    public function adapter(string $reportKey): RegulatoryReportAdapter
    {
        $definition = $this->definition($reportKey);
        $adapterClass = $definition['adapter'] ?? null;

        if (! is_string($adapterClass)) {
            throw new InvalidArgumentException("Regulatory report [{$reportKey}] is not configured.");
        }

        $adapter = app($adapterClass);

        if (! $adapter instanceof RegulatoryReportAdapter) {
            throw new InvalidArgumentException("Regulatory report adapter [{$adapterClass}] must implement RegulatoryReportAdapter.");
        }

        return $adapter;
    }

    public function context(?School $school): array
    {
        $availableDefinitions = $this->availableDefinitions($school);

        return [
            'country_code' => $school?->country_code,
            'available_report_keys' => array_keys($availableDefinitions),
            'agencies' => array_values(array_unique(array_filter(array_column($availableDefinitions, 'agency')))),
            'definitions' => array_values(array_map($this->publicDefinition(...), $this->definitions())),
        ];
    }

    public function isAvailable(string $reportKey, ?School $school): bool
    {
        return array_key_exists($reportKey, $this->availableDefinitions($school));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function availableDefinitions(?School $school): array
    {
        if (! $school instanceof School) {
            return [];
        }

        return array_filter(
            $this->definitions(),
            fn (array $definition): bool => $this->matchesSchool($definition, $school),
        );
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function matchesSchool(array $definition, School $school): bool
    {
        $countryCode = $definition['country_code'] ?? null;
        if (is_string($countryCode) && $school->country_code !== $countryCode) {
            return false;
        }

        $framework = $definition['framework'] ?? null;
        if (! is_string($framework)) {
            return true;
        }

        return $school->curriculumCapabilities()
            ->where('is_enabled', true)
            ->where('curriculum_framework', $framework)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function publicDefinition(array $definition): array
    {
        unset($definition['adapter'], $definition['enabled']);

        return $definition;
    }
}
