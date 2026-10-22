<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Guards the runtime versions that carry the October 2026 security fixes.
 *
 * PHP 8.5.12 resolves two unauthenticated remote code execution flaws that
 * PHP published on 21 October 2026:
 *
 * - CVE-2026-109618 (GHSA-w8hw-w9fx-j79w): out-of-memory corruption during
 *   request startup. A request body that passes the post_max_size check but
 *   exhausts memory_limit aborts the request while its memory is only partly
 *   initialised, and the shutdown procedure then operates on that state.
 * - CVE-2026-109619 (GHSA-6wmf-4rv5-p3rp): stale worker state. Request owned
 *   state published by one request can survive into the next request handled
 *   by the same worker, producing a use after free.
 *
 * The PHP project states that upgrading is the only complete mitigation, so a
 * deployment running an unpatched interpreter must not serve traffic.
 */
final class RuntimeSecurity
{
    /**
     * The first PHP release carrying the CVE-2026-109618 and CVE-2026-109619 fixes.
     */
    public const MINIMUM_PHP_VERSION = '8.5.12';

    /**
     * The first Node.js 22 release carrying the October 2026 security fixes.
     */
    public const MINIMUM_NODE_VERSION = '22.23.4';

    public static function phpVersionIsSupported(?string $version = null): bool
    {
        return version_compare($version ?? PHP_VERSION, self::MINIMUM_PHP_VERSION, '>=');
    }

    public static function nodeVersionIsSupported(?string $version): bool
    {
        if (! is_string($version) || mb_trim($version) === '') {
            return false;
        }

        $parts = explode('.', mb_trim($version));

        if (count($parts) < 2) {
            return false;
        }

        $major = (int) $parts[0];
        $minor = (int) $parts[1];
        $patch = (int) ($parts[2] ?? 0);

        if ($major !== 22) {
            // Any other release line carries its own patched 22.x equivalent.
            return $major > 22;
        }

        return $minor > 23 || ($minor === 23 && $patch >= 4);
    }

    public static function phpVersionMessage(?string $version = null): string
    {
        return sprintf(
            'PHP %s is running, but %s or newer is required. PHP %s fixes CVE-2026-109618 and CVE-2026-109619, both critical unauthenticated remote code execution flaws published on 21 October 2026.',
            $version ?? PHP_VERSION,
            self::MINIMUM_PHP_VERSION,
            self::MINIMUM_PHP_VERSION,
        );
    }

    public static function nodeVersionMessage(string $version): string
    {
        return sprintf(
            'Node.js %s is running, but %s or newer is required. The October 2026 release fixes CVE-2026-111497, a Brotli decoder out-of-bounds write that permits remote code execution, alongside the CVE-2026-106069 and CVE-2026-105482 hostname and DNS resolution flaws.',
            $version,
            self::MINIMUM_NODE_VERSION,
        );
    }
}
