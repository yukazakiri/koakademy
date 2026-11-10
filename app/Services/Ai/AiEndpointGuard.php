<?php

declare(strict_types=1);

namespace App\Services\Ai;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

use function mb_strlen;

/**
 * Validates administrator-supplied AI provider base URLs before they are used
 * for server-side requests.
 *
 * Public endpoints must use HTTPS and resolve only to public addresses. Plain
 * HTTP and private, loopback, or link-local destinations are rejected unless
 * the deployment operator explicitly allowlists the host through
 * AI_ALLOWED_PRIVATE_HOSTS. Cloud metadata endpoints are always rejected.
 */
final class AiEndpointGuard
{
    public const string REASON_INVALID = 'invalid';

    public const string REASON_SCHEME = 'scheme';

    public const string REASON_BLOCKED = 'blocked';

    public const string REASON_PRIVATE = 'private';

    public const string REASON_UNRESOLVED = 'unresolved';

    /**
     * Ranges that may never be reached, even when allowlisted.
     *
     * @var list<string>
     */
    private const array ALWAYS_BLOCKED_RANGES = [
        '100.100.100.200/32', // Alibaba Cloud instance metadata
        '169.254.0.0/16',     // IPv4 link-local, including AWS/Azure/GCP metadata
        '::ffff:169.254.0.0/112', // IPv4-mapped link-local
        'fd00:ec2::254/128',  // AWS instance metadata over IPv6
        'fe80::/10',          // IPv6 link-local
    ];

    /**
     * Address ranges that are not publicly routable.
     *
     * @var list<string>
     */
    private const array NON_PUBLIC_RANGES = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.168.0.0/16',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '240.0.0.0/4',
        '255.255.255.255/32',
        '::/128',
        '::1/128',
        '::ffff:0:0/96',
        '64:ff9b::/96',
        '100::/64',
        '2001::/32',
        '2001:10::/28',
        '2001:db8::/32',
        '2002::/16',
        'fc00::/7',
        'fe80::/10',
        'ff00::/8',
    ];

    /**
     * @var list<string>
     */
    private const array METADATA_HOSTS = [
        'instance-data',
        'instance-data.ec2.internal',
        'metadata',
        'metadata.google.internal',
        'metadata.goog',
    ];

    /**
     * Resolved addresses for this request lifecycle, keyed by host.
     *
     * @var array<string, list<string>>
     */
    private array $resolvedHosts = [];

    /**
     * Trim a base URL and default a missing scheme to HTTPS rather than HTTP.
     */
    public function normalize(string $url): string
    {
        $url = mb_trim($url);

        if ($url === '') {
            return '';
        }

        if (str_starts_with($url, '//')) {
            $url = 'https:'.$url;
        } elseif (preg_match('~^[a-z][a-z0-9+.\-]*://~i', $url) !== 1) {
            $url = 'https://'.$url;
        }

        return mb_rtrim($url, '/');
    }

    public function isAllowed(string $url, bool $resolveDns = true): bool
    {
        return $this->unsafeReason($this->normalize($url), $resolveDns) === null;
    }

    /**
     * Whether the URL is the shipped default for a built-in provider.
     *
     * Provider defaults are constants owned by the application, so they are
     * accepted without a DNS lookup. This keeps the default Ollama endpoint
     * (plain HTTP on loopback) working while every administrator-supplied
     * replacement still has to pass the strict checks.
     */
    public function isProviderDefaultUrl(string $providerKey, string $url): bool
    {
        $default = AiSettingsService::supportedProviders()[$providerKey]['default_url'] ?? null;

        if (! is_string($default) || $default === '') {
            return false;
        }

        return $this->normalize($url) === $this->normalize($default);
    }

    /**
     * Whether the URL is a shipped provider endpoint, including a path under
     * its default base URL. The default Ollama endpoint is the only private
     * destination covered here; it is product-owned and never admin-supplied.
     */
    public function isProviderDefaultEndpoint(string $url): bool
    {
        $normalized = $this->normalize($url);

        foreach (AiSettingsService::supportedProviders() as $meta) {
            $default = $meta['default_url'] ?? null;

            if (! is_string($default) || $default === '') {
                continue;
            }

            $default = $this->normalize($default);

            if ($normalized === $default || str_starts_with($normalized, $default.'/')) {
                return true;
            }
        }

        return false;
    }

    public function assertSafe(string $url, bool $resolveDns = true): void
    {
        $normalized = $this->normalize($url);
        $reason = $this->unsafeReason($normalized, $resolveDns);

        if ($reason === null) {
            return;
        }

        Log::warning('Blocked unsafe AI provider endpoint.', [
            'reason' => $reason,
            'host' => $this->host($normalized),
        ]);

        throw new InvalidArgumentException($this->rejectionMessage($reason));
    }

    /**
     * Return the validated addresses to pin for the request. DNS rebinding
     * between validation and dispatch is otherwise possible when the client
     * resolves the host again.
     *
     * @return list<string>
     */
    public function validatedAddresses(string $url): array
    {
        $normalized = $this->normalize($url);
        $this->assertSafe($normalized);

        $host = $this->host($normalized);

        if ($host === null) {
            return [];
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        return $this->resolve($host);
    }

    /**
     * Return a rejection reason, or null when the URL is safe to contact.
     */
    public function unsafeReason(string $url, bool $resolveDns = true): ?string
    {
        if ($url === '') {
            return self::REASON_INVALID;
        }

        // Whitespace, control characters, and backslashes are parsed
        // differently by libcurl and PHP, so reject them outright.
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $url) === 1) {
            return self::REASON_INVALID;
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            return self::REASON_INVALID;
        }

        $scheme = mb_strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return self::REASON_SCHEME;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return self::REASON_INVALID;
        }

        $host = $this->normalizeHost((string) ($parts['host'] ?? ''));

        if ($host === '') {
            return self::REASON_INVALID;
        }

        if (isset($parts['port']) && ((int) $parts['port'] < 1 || (int) $parts['port'] > 65535)) {
            return self::REASON_INVALID;
        }

        if ($this->isMetadataHost($host)) {
            return self::REASON_BLOCKED;
        }

        $hostIsIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
        $hostAllowed = $this->hostIsAllowlisted($host) || ($hostIsIp && $this->ipIsAllowlisted($host));

        if ($scheme === 'http' && ! $hostAllowed) {
            return self::REASON_SCHEME;
        }

        if ($hostIsIp) {
            if ($this->isAlwaysBlockedIp($host)) {
                return self::REASON_BLOCKED;
            }

            if ($this->isNonPublicIp($host) && ! $hostAllowed) {
                return self::REASON_PRIVATE;
            }

            return null;
        }

        if (preg_match('/^[a-z0-9]([a-z0-9._-]*[a-z0-9])?$/i', $host) !== 1) {
            return self::REASON_INVALID;
        }

        // Request validation only checks the URL shape. DNS is resolved again
        // immediately before dispatch so the saved host is re-checked then.
        if (! $resolveDns) {
            return null;
        }

        $addresses = $this->resolve($host);

        if ($addresses === []) {
            return $hostAllowed ? null : self::REASON_UNRESOLVED;
        }

        foreach ($addresses as $address) {
            if ($this->isAlwaysBlockedIp($address)) {
                return self::REASON_BLOCKED;
            }

            if ($this->isNonPublicIp($address) && ! $hostAllowed && ! $this->ipIsAllowlisted($address)) {
                return self::REASON_PRIVATE;
            }
        }

        return null;
    }

    public function rejectionMessage(string $reason): string
    {
        return match ($reason) {
            self::REASON_SCHEME => 'The provider base URL must use HTTPS. Plain HTTP is only allowed for operator-allowlisted hosts.',
            self::REASON_BLOCKED => 'The provider base URL points to a blocked network address.',
            self::REASON_PRIVATE => 'The provider base URL must not point to a private, loopback, link-local, or reserved network address.',
            self::REASON_UNRESOLVED => 'The provider host could not be resolved.',
            default => 'The provider base URL is invalid.',
        };
    }

    private function host(string $url): ?string
    {
        $parts = parse_url($url);

        if (! is_array($parts)) {
            return null;
        }

        $host = $this->normalizeHost((string) ($parts['host'] ?? ''));

        return $host === '' ? null : $host;
    }

    private function normalizeHost(string $host): string
    {
        $host = mb_strtolower(mb_trim($host));
        $host = mb_trim($host, '[]');

        return mb_rtrim($host, '.');
    }

    private function isMetadataHost(string $host): bool
    {
        return in_array($host, self::METADATA_HOSTS, true)
            || str_ends_with($host, '.metadata.google.internal');
    }

    private function isAlwaysBlockedIp(string $ip): bool
    {
        foreach (self::ALWAYS_BLOCKED_RANGES as $range) {
            if ($this->ipInCidr($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    private function isNonPublicIp(string $ip): bool
    {
        // PHP already knows most special-use ranges; the list above adds
        // CGNAT, multicast, documentation, and IPv6 transition ranges.
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }

        foreach (self::NON_PUBLIC_RANGES as $range) {
            if ($this->ipInCidr($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    private function hostIsAllowlisted(string $host): bool
    {
        $allowlist = $this->allowlist();

        if (in_array($host, $allowlist['hosts'], true)) {
            return true;
        }

        foreach ($allowlist['wildcards'] as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                return true;
            }
        }

        return false;
    }

    private function ipIsAllowlisted(string $ip): bool
    {
        $allowlist = $this->allowlist();

        if (in_array($ip, $allowlist['ips'], true)) {
            return true;
        }

        foreach ($allowlist['cidrs'] as $cidr) {
            if ($this->ipInCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{hosts: list<string>, wildcards: list<string>, ips: list<string>, cidrs: list<string>}
     */
    private function allowlist(): array
    {
        $configured = config('ai.security.allowed_private_hosts', []);

        if (is_string($configured)) {
            $configured = explode(',', $configured);
        }

        if (! is_array($configured)) {
            $configured = [];
        }

        $hosts = [];
        $wildcards = [];
        $ips = [];
        $cidrs = [];

        foreach ($configured as $entry) {
            if (! is_string($entry)) {
                continue;
            }

            $entry = mb_strtolower(mb_trim($entry));

            if ($entry === '') {
                continue;
            }

            if (str_contains($entry, '://')) {
                $entry = (string) (parse_url($entry, PHP_URL_HOST) ?? '');
            }

            $entry = mb_trim($entry, '[]');

            if (str_contains($entry, '/')) {
                [$network, $bits] = array_pad(explode('/', $entry, 2), 2, '');

                if (filter_var($network, FILTER_VALIDATE_IP) !== false && ctype_digit($bits)) {
                    $cidrs[] = $entry;
                }

                continue;
            }

            // Accept host:port entries while leaving bare IPv6 addresses intact.
            if (mb_substr_count($entry, ':') === 1) {
                [$entry, $port] = explode(':', $entry, 2);

                if (! ctype_digit($port)) {
                    continue;
                }
            }

            if (filter_var($entry, FILTER_VALIDATE_IP) !== false) {
                $ips[] = $entry;

                continue;
            }

            if (str_starts_with($entry, '*.')) {
                $wildcards[] = mb_rtrim(mb_substr($entry, 2), '.');

                continue;
            }

            if (preg_match('/^[a-z0-9]([a-z0-9._-]*[a-z0-9])?$/i', $entry) === 1) {
                $hosts[] = $entry;
            }
        }

        return [
            'hosts' => array_values(array_unique($hosts)),
            'wildcards' => array_values(array_unique($wildcards)),
            'ips' => array_values(array_unique($ips)),
            'cidrs' => array_values(array_unique($cidrs)),
        ];
    }

    /**
     * @return list<string>
     */
    private function resolve(string $host): array
    {
        if (array_key_exists($host, $this->resolvedHosts)) {
            return $this->resolvedHosts[$host];
        }

        $addresses = [];

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if (is_array($records)) {
            foreach ($records as $record) {
                foreach (['ip', 'ipv6'] as $key) {
                    if (isset($record[$key]) && is_string($record[$key])) {
                        $addresses[] = $record[$key];
                    }
                }
            }
        }

        // dns_get_record() does not consult /etc/hosts, and libc also accepts
        // legacy numeric IPv4 forms such as 127.1 and 2130706433.
        if ($addresses === []) {
            $ipv4 = @gethostbynamel($host);

            if (is_array($ipv4)) {
                $addresses = $ipv4;
            }
        }

        $addresses = array_values(array_unique(array_filter(
            $addresses,
            static fn (string $address): bool => filter_var($address, FILTER_VALIDATE_IP) !== false,
        )));

        return $this->resolvedHosts[$host] = $addresses;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$network, $bits] = array_pad(explode('/', $cidr, 2), 2, '');

        $ipBinary = @inet_pton($ip);
        $networkBinary = @inet_pton($network);

        if ($ipBinary === false || $networkBinary === false || mb_strlen($ipBinary, '8bit') !== mb_strlen($networkBinary, '8bit')) {
            return false;
        }

        $bits = (int) $bits;
        $maxBits = mb_strlen($ipBinary, '8bit') * 8;

        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }

        $wholeBytes = intdiv($bits, 8);
        $remainingBits = $bits % 8;

        if ($wholeBytes > 0 && mb_substr($ipBinary, 0, $wholeBytes, '8bit') !== mb_substr($networkBinary, 0, $wholeBytes, '8bit')) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($ipBinary[$wholeBytes]) & $mask) === (ord($networkBinary[$wholeBytes]) & $mask);
    }
}
