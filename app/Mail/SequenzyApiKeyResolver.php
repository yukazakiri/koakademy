<?php

declare(strict_types=1);

namespace App\Mail;

final class SequenzyApiKeyResolver
{
    public function resolve(): ?string
    {
        $configuredKey = config('services.sequenzy.key')
            ?: config('services.sequenzy.legacy_key');

        return filled($configuredKey) ? mb_trim((string) $configuredKey) : null;
    }
}
