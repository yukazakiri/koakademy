<?php

declare(strict_types=1);

namespace App\Services\Newsletter\Providers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

abstract class AbstractNewsletterProvider
{
    /** @param array<string, mixed> $configuration */
    public function __construct(protected readonly array $configuration) {}

    protected function client(string $baseUrl): PendingRequest
    {
        $timeout = (float) config('newsletter.timeout', 10);
        $connectTimeout = (float) config('newsletter.connect_timeout', 5);

        return Http::baseUrl(mb_rtrim($baseUrl, '/'))
            ->acceptJson()
            ->asJson()
            ->timeout($timeout)
            ->connectTimeout($connectTimeout);
    }
}
