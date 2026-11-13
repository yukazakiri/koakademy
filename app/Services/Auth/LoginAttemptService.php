<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Cache\RateLimiter;

/**
 * Per-account lockout for password authentication.
 *
 * A rate limiter on its own only slows an attacker down for the length of one
 * window; it does not stop a targeted guess against a single account. This
 * service keeps a counter of failed sign-ins for an "email|ip" pair and locks
 * that pair once the allowed number of attempts is used up, which is what
 * turns unlimited password guessing into a bounded number of guesses.
 *
 * The pair is scoped to the address the failures came from on purpose. A lock
 * that ignored the address would let anyone lock every account in the school
 * out of its own session by sending a handful of wrong passwords per user.
 */
final class LoginAttemptService
{
    public function __construct(private readonly RateLimiter $limiter) {}

    /**
     * Determine whether the email address and IP pair is locked out.
     */
    public function tooManyAttempts(string $email, string $ip): bool
    {
        return $this->limiter->tooManyAttempts($this->key($email, $ip), $this->maxAttempts());
    }

    /**
     * Record a failed sign-in against the email address and IP pair.
     */
    public function recordFailure(string $email, string $ip): void
    {
        $this->limiter->hit($this->key($email, $ip), $this->decaySeconds());
    }

    /**
     * Clear the failure counter after a successful sign-in.
     */
    public function recordSuccess(string $email, string $ip): void
    {
        $this->limiter->clear($this->key($email, $ip));
    }

    /**
     * The number of failed sign-ins recorded for the email address and IP pair.
     */
    public function attempts(string $email, string $ip): int
    {
        return (int) $this->limiter->attempts($this->key($email, $ip));
    }

    /**
     * The number of attempts still available before the pair is locked out.
     */
    public function remainingAttempts(string $email, string $ip): int
    {
        return max(0, $this->maxAttempts() - $this->attempts($email, $ip));
    }

    /**
     * The number of seconds until the lockout expires.
     */
    public function availableIn(string $email, string $ip): int
    {
        return $this->limiter->availableIn($this->key($email, $ip));
    }

    /**
     * The number of failed sign-ins allowed before a lockout.
     */
    public function maxAttempts(): int
    {
        return max(1, (int) config('security.login_max_attempts', 5));
    }

    /**
     * The lifetime of the failure counter, in seconds.
     */
    private function decaySeconds(): int
    {
        return max(60, (int) config('security.lockout_minutes', 15) * 60);
    }

    private function key(string $email, string $ip): string
    {
        return 'login:'.mb_strtolower(mb_trim($email)).'|'.$ip;
    }
}
