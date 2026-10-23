<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

/**
 * Bounds the page size an API or administrator endpoint may return.
 *
 * A request may ask for any page size, so every list endpoint funnels its
 * `per_page` input through this helper. The configured ceiling stops a single
 * query parameter from hydrating an unbounded number of models in an Octane
 * worker, while the configured default keeps endpoints that receive no page
 * size consistent.
 */
final class ApiPagination
{
    /**
     * The page size used when none is requested.
     */
    public const int DEFAULT_PER_PAGE = 20;

    /**
     * The largest page size an endpoint may return.
     */
    public const int MAX_PER_PAGE = 100;

    /**
     * Resolve a safe page size from the requested value.
     *
     * Values that are absent, non-numeric, or below one fall back to the
     * configured default. `all` is accepted as a request for the largest page
     * the endpoint is allowed to return rather than every matching row.
     */
    public static function perPage(mixed $requested = null): int
    {
        $maximum = self::maxPerPage();

        if (is_string($requested) && mb_strtolower(mb_trim($requested)) === 'all') {
            return $maximum;
        }

        if (! is_numeric($requested)) {
            return min(self::defaultPerPage(), $maximum);
        }

        return max(1, min((int) $requested, $maximum));
    }

    /**
     * The configured default page size.
     */
    public static function defaultPerPage(): int
    {
        return max(1, self::configured('api.default_per_page', self::DEFAULT_PER_PAGE));
    }

    /**
     * The configured maximum page size.
     */
    public static function maxPerPage(): int
    {
        return max(1, self::configured('api.max_per_page', self::MAX_PER_PAGE));
    }

    private static function configured(string $key, int $fallback): int
    {
        $value = config($key, $fallback);

        return is_numeric($value) ? (int) $value : $fallback;
    }
}
