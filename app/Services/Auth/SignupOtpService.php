<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;

/**
 * Verification codes issued while signing up.
 *
 * The code and the number of wrong guesses live under a single cache key, so
 * a six character code cannot be guessed indefinitely. Every wrong guess
 * increments the counter, and the challenge is burned once the allowed number
 * of attempts is used up, which forces the visitor to request a new code
 * instead of working through the key space.
 */
final class SignupOtpService
{
    private const string CACHE_PREFIX = 'signup_otp_';

    private const int TTL_SECONDS = 600;

    /**
     * Issue a verification code for the email address.
     */
    public function issue(string $email, string $code): void
    {
        Cache::put($this->key($email), [
            'code' => $code,
            'attempts' => 0,
            'locked' => false,
            'expires_at' => now()->addSeconds(self::TTL_SECONDS)->getTimestamp(),
        ], self::TTL_SECONDS);
    }

    /**
     * Check a submitted code and count the attempt.
     *
     * A wrong code never succeeds and always counts. The challenge is marked
     * as spent once the allowed number of attempts is used up, so the correct
     * code stops working too until a new one is requested.
     */
    public function verify(string $email, string $code): bool
    {
        $payload = Cache::get($this->key($email));

        // Codes issued before the attempt counter existed were stored as a
        // plain string. They keep working, and the first wrong guess turns
        // them into a counted challenge.
        $stored = is_array($payload) ? ($payload['code'] ?? null) : $payload;
        $attempts = is_array($payload) ? (int) ($payload['attempts'] ?? 0) : 0;
        $locked = is_array($payload) && (bool) ($payload['locked'] ?? false);
        $expiresAt = is_array($payload) ? (int) ($payload['expires_at'] ?? 0) : 0;

        if ($locked || ! is_string($stored) || $stored === '') {
            return false;
        }

        if (hash_equals($stored, (string) $code)) {
            return true;
        }

        $attempts++;
        $locked = $attempts >= $this->maxAttempts();

        $remaining = $expiresAt > 0
            ? max(1, $expiresAt - now()->getTimestamp())
            : self::TTL_SECONDS;

        Cache::put($this->key($email), [
            'code' => $stored,
            'attempts' => $attempts,
            'locked' => $locked,
            'expires_at' => $expiresAt > 0 ? $expiresAt : now()->addSeconds(self::TTL_SECONDS)->getTimestamp(),
        ], $remaining);

        return false;
    }

    /**
     * Determine whether the challenge ran out of attempts.
     */
    public function exhausted(string $email): bool
    {
        $payload = Cache::get($this->key($email));

        return is_array($payload) && (bool) ($payload['locked'] ?? false);
    }

    /**
     * The number of wrong guesses recorded for the challenge.
     */
    public function attempts(string $email): int
    {
        $payload = Cache::get($this->key($email));

        return is_array($payload) ? (int) ($payload['attempts'] ?? 0) : 0;
    }

    /**
     * Discard the challenge for the email address.
     */
    public function forget(string $email): void
    {
        Cache::forget($this->key($email));
    }

    /**
     * The number of wrong guesses allowed before the challenge is burned.
     */
    public function maxAttempts(): int
    {
        return max(1, (int) config('security.otp_max_attempts', 5));
    }

    private function key(string $email): string
    {
        return self::CACHE_PREFIX.mb_strtolower(mb_trim($email));
    }
}
