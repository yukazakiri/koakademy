<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Cache\RateLimiter;

/**
 * Attempt counter for a two-factor challenge.
 *
 * A six digit authenticator code has a million possible values, so the
 * challenge itself has to be the thing that is bounded: every wrong code
 * counts against the challenge, and once the allowed number of attempts is
 * used up the challenge is destroyed and the visitor has to sign in again.
 *
 * The counter is scoped to the challenge (the account and the session that
 * started it) so that one visitor cannot burn another visitor's attempts.
 */
final class TwoFactorAttemptService
{
    public function __construct(private readonly RateLimiter $limiter) {}

    /**
     * Determine whether the challenge has used up its attempts.
     */
    public function tooManyAttempts(string $challenge): bool
    {
        return $this->limiter->tooManyAttempts($this->key($challenge), $this->maxAttempts());
    }

    /**
     * Record a wrong code against the challenge.
     */
    public function recordFailure(string $challenge): void
    {
        $this->limiter->hit($this->key($challenge), $this->decaySeconds());
    }

    /**
     * Clear the counter after the challenge was answered correctly.
     */
    public function recordSuccess(string $challenge): void
    {
        $this->limiter->clear($this->key($challenge));
    }

    /**
     * The number of wrong codes recorded for the challenge.
     */
    public function attempts(string $challenge): int
    {
        return (int) $this->limiter->attempts($this->key($challenge));
    }

    /**
     * The number of wrong codes allowed before the challenge is destroyed.
     */
    public function maxAttempts(): int
    {
        return max(1, (int) config('security.two_factor_max_attempts', 5));
    }

    /**
     * The lifetime of the counter, in seconds.
     */
    private function decaySeconds(): int
    {
        return max(60, (int) config('security.lockout_minutes', 15) * 60);
    }

    private function key(string $challenge): string
    {
        return 'two-factor:'.$challenge;
    }
}
