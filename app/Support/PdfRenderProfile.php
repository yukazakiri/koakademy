<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class PdfRenderProfile
{
    /**
     * Chrome CLI flag keys that should be stripped when the active driver
     * is not Browsershot (they are only meaningful for headless Chromium).
     */
    private const BROWSERSHOT_ONLY_KEYS = [
        'headless',
        'no-sandbox',
        'disable-dev-shm-usage',
        'disable-gpu',
        'no-first-run',
        'disable-background-timer-throttling',
        'disable-backgrounding-occluded-windows',
        'disable-renderer-backgrounding',
        'print-to-pdf-no-header',
        'run-all-compositor-stages-before-draw',
        'disable-extensions',
        'virtual-time-budget',
    ];

    /**
     * Resolve a named profile to an options array, filtering out
     * driver-incompatible keys when necessary.
     *
     * @return array<string, mixed>
     */
    public static function resolve(string $profileName, ?string $driver = null): array
    {
        $profiles = config('pdf-profiles.profiles', []);

        if (! array_key_exists($profileName, $profiles)) {
            throw new InvalidArgumentException(
                "PDF render profile '{$profileName}' not found in config/pdf-profiles.php"
            );
        }

        $options = $profiles[$profileName];

        $effectiveDriver = $driver ?? config('laravel-pdf.driver', 'dompdf');

        if ($effectiveDriver !== 'browsershot') {
            $options = array_diff_key($options, array_flip(self::BROWSERSHOT_ONLY_KEYS));
        }

        return $options;
    }

    /**
     * Merge a named profile with additional overrides.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function resolveWithOverrides(string $profileName, array $overrides, ?string $driver = null): array
    {
        return array_merge(self::resolve($profileName, $driver), $overrides);
    }

    /**
     * Return all registered profile names.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_keys(config('pdf-profiles.profiles', []));
    }
}
