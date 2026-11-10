<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\Ai\AiEndpointGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects provider base URLs that could reach private or metadata networks.
 *
 * Hostnames are resolved here, when settings are saved, and again immediately
 * before each dispatch. Saving a host that currently resolves to a private
 * address is therefore refused rather than deferred to request time.
 */
final class SafeAiBaseUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            $fail('The provider base URL is invalid.');

            return;
        }

        $guard = app(AiEndpointGuard::class);
        $providerKey = $this->builtInProviderKey($attribute);

        if ($providerKey !== null && $guard->isProviderDefaultUrl($providerKey, $value)) {
            return;
        }

        $reason = $guard->unsafeReason($guard->normalize($value));

        if ($reason !== null) {
            $fail($guard->rejectionMessage($reason));
        }
    }

    /**
     * Built-in providers are addressed as providers.{key}.base_url. Custom
     * providers have no shipped default and always go through the strict check.
     */
    private function builtInProviderKey(string $attribute): ?string
    {
        if (preg_match('/^providers\.([^.]+)\.base_url$/', $attribute, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
