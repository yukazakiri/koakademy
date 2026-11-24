<?php

declare(strict_types=1);

namespace App\Ai\Mcp;

use App\Services\Ai\AiEndpointGuard;
use Illuminate\Http\Client\PendingRequest;
use Laravel\Mcp\Client\Transport\HttpTransport;

/**
 * Pins validated DNS addresses onto every outbound MCP HTTP request so a host
 * cannot rebind to a private or metadata address between validation and connect.
 */
final class PinnedHttpTransport extends HttpTransport
{
    public function __construct(
        string $url,
        private readonly ?AiEndpointGuard $endpointGuard = null,
    ) {
        parent::__construct($url);
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function request(array $headers = []): PendingRequest
    {
        $guard = $this->endpointGuard ?? app(AiEndpointGuard::class);
        $addresses = $guard->validatedAddresses($this->url);
        $resolved = $this->curlResolveOptions($this->url, $addresses);

        $request = parent::request($headers);

        if ($resolved !== []) {
            $request = $request->withOptions(['curl' => [CURLOPT_RESOLVE => $resolved]]);
        }

        return $request;
    }

    /**
     * Pin every validated address in a single CURLOPT_RESOLVE entry so libcurl
     * keeps both IPv4 and IPv6 candidates without re-querying DNS.
     *
     * @param  list<string>  $addresses
     * @return list<string>
     */
    private function curlResolveOptions(string $endpoint, array $addresses): array
    {
        if ($addresses === [] || ! extension_loaded('curl') || ! defined('CURLOPT_RESOLVE')) {
            return [];
        }

        $parts = parse_url($endpoint);

        if (! is_array($parts)) {
            return [];
        }

        $host = mb_trim((string) ($parts['host'] ?? ''), '[]');

        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [];
        }

        $port = (int) ($parts['port'] ?? (mb_strtolower((string) ($parts['scheme'] ?? '')) === 'https' ? 443 : 80));

        $formatted = array_map(
            static fn (string $address): string => str_contains($address, ':') ? '['.$address.']' : $address,
            $addresses,
        );

        return [$host.':'.$port.':'.implode(',', $formatted)];
    }
}
