<?php

declare(strict_types=1);

namespace App\Ai\Mcp;

use App\Services\Ai\AiEndpointGuard;
use GuzzleHttp\Handler\CurlHandler;
use Illuminate\Http\Client\PendingRequest;
use InvalidArgumentException;
use Laravel\Mcp\Client\Transport\HttpTransport;
use RuntimeException;

/**
 * Forces every outbound MCP HTTP request through Guzzle's CurlHandler and pins
 * validated DNS addresses via CURLOPT_RESOLVE so streamed requests cannot fall
 * back to StreamHandler or rebind DNS between validation and connect.
 */
final class PinnedHttpTransport extends HttpTransport
{
    public function __construct(
        string $url,
        private readonly ?AiEndpointGuard $endpointGuard = null,
        private readonly ?bool $curlPinningSupported = null,
    ) {
        parent::__construct($url);
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function request(array $headers = []): PendingRequest
    {
        if (! $this->supportsCurlPinning()) {
            throw new RuntimeException('Pinned MCP HTTP connections require the PHP cURL extension with CURLOPT_RESOLVE support.');
        }

        $guard = $this->endpointGuard ?? app(AiEndpointGuard::class);
        $addresses = $guard->validatedAddresses($this->url);
        $resolved = $this->curlResolveOptions($this->url, $addresses);

        // HttpTransport::send() sets ['stream' => true], which makes Guzzle's
        // default handler route to StreamHandler and silently ignore
        // CURLOPT_RESOLVE. Binding CurlHandler directly ensures every request,
        // streamed or not, connects only to the validated pinned addresses.
        $request = parent::request($headers)->setHandler(new CurlHandler);

        if ($resolved !== []) {
            $request = $request->withOptions(['curl' => [CURLOPT_RESOLVE => $resolved]]);
        }

        return $request;
    }

    private function supportsCurlPinning(): bool
    {
        if ($this->curlPinningSupported !== null) {
            return $this->curlPinningSupported;
        }

        return extension_loaded('curl')
            && function_exists('curl_exec')
            && defined('CURLOPT_RESOLVE');
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
        $parts = parse_url($endpoint);

        if (! is_array($parts)) {
            throw new InvalidArgumentException('The MCP endpoint URL is invalid.');
        }

        $host = mb_trim((string) ($parts['host'] ?? ''), '[]');

        if ($host === '') {
            throw new InvalidArgumentException('The MCP endpoint URL is invalid.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [];
        }

        if ($addresses === []) {
            throw new InvalidArgumentException('The MCP endpoint host could not be resolved for DNS pinning.');
        }

        $port = (int) ($parts['port'] ?? (mb_strtolower((string) ($parts['scheme'] ?? '')) === 'https' ? 443 : 80));

        $formatted = array_map(
            static fn (string $address): string => str_contains($address, ':') ? '['.$address.']' : $address,
            $addresses,
        );

        return [$host.':'.$port.':'.implode(',', $formatted)];
    }
}
