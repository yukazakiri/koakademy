<?php

declare(strict_types=1);

namespace App\Support;

final class HostingSecurity
{
    /**
     * Return exact-match host patterns derived from the public application URLs.
     *
     * @return list<string>
     */
    public static function trustedHostPatterns(
        ?string $appUrl = null,
        ?string $adminHost = null,
        ?string $portalHost = null,
        ?string $additionalHosts = null,
    ): array {
        $hosts = [
            self::hostFromUrl($appUrl ?? self::environment('APP_URL', 'http://localhost')),
            $adminHost ?? self::environment('ADMIN_HOST', 'localhost'),
            $portalHost ?? self::environment('PORTAL_HOST', 'localhost'),
            ...explode(',', $additionalHosts ?? self::environment('TRUSTED_HOSTS', '')),
            'localhost',
            '127.0.0.1',
        ];

        $patterns = [];

        foreach ($hosts as $host) {
            $normalized = self::normalizeHost($host);

            if ($normalized === null) {
                continue;
            }

            $patterns[] = '^'.preg_quote($normalized, '/').'$';
        }

        return array_values(array_unique($patterns));
    }

    /**
     * @return array<int, string>|string|null
     */
    public static function trustedProxies(?string $proxies = null): array|string|null
    {
        $configured = mb_trim($proxies ?? self::environment('TRUSTED_PROXIES', '*'));

        if ($configured === '' || $configured === '*') {
            return $configured === '*' ? '*' : null;
        }

        return array_values(array_filter(array_map(
            mb_trim(...),
            explode(',', $configured),
        )));
    }

    public static function usesHttps(string $url): bool
    {
        return mb_strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
    }

    private static function hostFromUrl(string $url): string
    {
        return (string) (parse_url($url, PHP_URL_HOST) ?: $url);
    }

    private static function normalizeHost(string $host): ?string
    {
        $host = mb_strtolower(mb_trim($host));

        if ($host === '') {
            return null;
        }

        if (str_contains($host, '://')) {
            $host = self::hostFromUrl($host);
        }

        $host = mb_trim($host, "[] \t\n\r\0\x0B");
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        if ($host === '' || (! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) && ! filter_var($host, FILTER_VALIDATE_IP))) {
            return null;
        }

        return $host;
    }

    private static function environment(string $key, string $default): string
    {
        $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
