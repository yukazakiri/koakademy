<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\SetupCatalogProvider;
use App\Enums\CurriculumFramework;
use App\Enums\SchoolLevel;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Locale;

/**
 * Resolves configured country providers per call, with no request state retained (Octane-safe).
 * An unregistered country has no framework/program bootstrap; standard setup still works.
 */
final class SetupCatalogRegistry
{
    public function __construct(private Container $container) {}

    public function provider(string $countryCode): ?SetupCatalogProvider
    {
        $class = config('setup_catalog.providers.'.mb_strtoupper($countryCode));

        if ($class === null) {
            return null;
        }

        if (! is_string($class) || ! is_a($class, SetupCatalogProvider::class, true)) {
            throw new InvalidArgumentException('Configured setup catalog provider must implement '.SetupCatalogProvider::class.'.');
        }

        return $this->container->make($class);
    }

    /** @return list<CurriculumFramework> */
    public function frameworks(string $countryCode, SchoolLevel $level): array
    {
        $countryCode = mb_strtoupper(mb_trim($countryCode));

        return array_values(array_filter(
            $this->provider($countryCode)?->frameworks($level) ?? [],
            fn (CurriculumFramework $framework): bool => $framework->countryCode() === $countryCode
                && in_array($level, $framework->schoolLevels(), true),
        ));
    }

    /** @return list<array{code: string, name: string}> */
    public function countries(): array
    {
        return array_map(
            fn (string $code): array => [
                'code' => $code,
                'name' => Locale::getDisplayRegion('und_'.$code, 'en') ?: $code,
            ],
            IsoAlpha2CountryCodes::codes(),
        );
    }

    /**
     * Only registered countries have an indexed catalog. The page can use an empty
     * catalog for all other ISO countries without navigating or losing form state.
     *
     * @return array<string, array<string, mixed>>
     */
    public function catalogsByCountry(): array
    {
        $catalogs = [];

        foreach (array_keys(config('setup_catalog.providers', [])) as $countryCode) {
            $normalizedCountryCode = mb_strtoupper(mb_trim($countryCode));
            $catalogs[$normalizedCountryCode] = $this->catalog($normalizedCountryCode, null);
        }

        return $catalogs;
    }

    /** @return array<string, mixed> */
    public function catalog(string $countryCode, ?SchoolLevel $level): array
    {
        $countryCode = mb_strtoupper(mb_trim($countryCode));
        $provider = $this->provider($countryCode);
        $levels = $level === null ? SchoolLevel::cases() : [$level];
        $frameworks = [];

        foreach ($levels as $schoolLevel) {
            foreach ($this->frameworks($countryCode, $schoolLevel) as $framework) {
                $frameworks[$framework->value] = $framework;
            }
        }

        $options = CurriculumFramework::optionsForFrontend();

        return array_replace([
            'as_of' => null,
            'school_levels' => SchoolLevel::optionsForFrontend(),
            'frameworks' => array_values(array_filter($options, fn (array $option): bool => isset($frameworks[$option['value']]))),
            'program_groups' => [],
            'ched' => [],
            'shs' => ['legacy' => [], 'revised' => []],
            'tesda' => [],
            'matatag' => ['phases' => [], 'learning_areas' => []],
            'calendars' => [],
        ], $provider !== null ? $this->catalogData($provider, $levels) : []);
    }

    /**
     * Without a selected level, combine the provider's data across supported levels
     * for the existing setup page; providers can override each key per level.
     *
     * @param  list<SchoolLevel>  $levels
     * @return array<string, mixed>
     */
    private function catalogData(SetupCatalogProvider $provider, array $levels): array
    {
        $data = [];

        foreach ($levels as $level) {
            $part = $provider->catalog($level);
            $data['as_of'] ??= $part['as_of'] ?? null;
            $data['calendars'] ??= $part['calendars'] ?? [];

            foreach ($part['program_groups'] ?? [] as $frameworkId => $groups) {
                $data['program_groups'][$frameworkId] = collect(array_merge($data['program_groups'][$frameworkId] ?? [], $groups))
                    ->unique('key')->values()->all();
            }

            foreach (['ched', 'tesda'] as $key) {
                $data[$key] = collect(array_merge($data[$key] ?? [], $part[$key] ?? []))->unique('key')->values()->all();
            }

            foreach (['legacy', 'revised'] as $key) {
                $data['shs'][$key] = array_merge($data['shs'][$key] ?? [], $part['shs'][$key] ?? []);
            }

            $data['matatag']['phases'] = collect(array_merge($data['matatag']['phases'] ?? [], $part['matatag']['phases'] ?? []))->unique('sy')->values()->all();
            $data['matatag']['learning_areas'] = array_replace($data['matatag']['learning_areas'] ?? [], $part['matatag']['learning_areas'] ?? []);
        }

        return $data;
    }
}
