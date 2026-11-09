<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;

final class EnvironmentFileWriter
{
    /**
     * Explicit allowlist of environment keys permitted for modification via system management.
     *
     * @var list<string>
     */
    public const ALLOWED_KEYS = [
        // Socialite credentials
        'FACEBOOK_CLIENT_ID',
        'FACEBOOK_CLIENT_SECRET',
        'FACEBOOK_REDIRECT_URI',
        'GOOGLE_CLIENT_ID',
        'GOOGLE_CLIENT_SECRET',
        'GOOGLE_REDIRECT_URI',
        'TWITTER_CLIENT_ID',
        'TWITTER_CLIENT_SECRET',
        'TWITTER_REDIRECT_URI',
        'GITHUB_CLIENT_ID',
        'GITHUB_CLIENT_SECRET',
        'GITHUB_REDIRECT_URI',
        'LINKEDIN_CLIENT_ID',
        'LINKEDIN_CLIENT_SECRET',
        'LINKEDIN_REDIRECT_URI',

        // Broadcasting / Pusher credentials
        'BROADCAST_CONNECTION',
        'PUSHER_APP_ID',
        'PUSHER_APP_KEY',
        'PUSHER_APP_SECRET',
        'PUSHER_APP_CLUSTER',
    ];

    /**
     * Update an environment file with the given key-value pairs under an exclusive lock.
     *
     * @param  array<string, string|int|float|bool|null>  $values
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function update(array $values, ?string $filePath = null): void
    {
        if ($filePath === null && app()->environment('testing')) {
            return;
        }

        $envPath = $filePath ?? base_path('.env');

        $directory = dirname($envPath);
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $handle = fopen($envPath, 'c+');
        if ($handle === false) {
            throw new RuntimeException("Unable to open environment file [{$envPath}] for writing.");
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException("Unable to acquire exclusive lock on environment file [{$envPath}].");
            }

            $stat = fstat($handle);
            $size = (int) ($stat['size'] ?? 0);
            $content = $size > 0 ? (fread($handle, $size) ?: '') : '';

            $updatedContent = $this->updateContent($content, $values);

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, $updatedContent);
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Update environment file content with validated and sanitized key-value pairs.
     *
     * @param  array<string, string|int|float|bool|null>  $values
     *
     * @throws InvalidArgumentException
     */
    public function updateContent(string $content, array $values): string
    {
        foreach ($values as $key => $value) {
            if ($value === null) {
                continue;
            }

            $this->validateKey($key);

            $stringValue = (string) $value;
            $this->validateValue($key, $stringValue);

            $formattedValue = $this->formatValue($stringValue);

            $pattern = '/^\s*'.preg_quote($key, '/').'\s*=.*/m';

            if (preg_match($pattern, $content)) {
                // Use preg_replace_callback to avoid preg_replace backreference expansion ($0, $1, \1, etc.)
                $content = preg_replace_callback(
                    $pattern,
                    static fn (): string => "{$key}={$formattedValue}",
                    $content
                ) ?? $content;
            } else {
                // Append if not exists
                $content = $content === ''
                    ? "{$key}={$formattedValue}\n"
                    : mb_rtrim($content, "\r\n")."\n{$key}={$formattedValue}\n";
            }
        }

        return $content;
    }

    /**
     * Validate key-value pairs without modifying any file.
     *
     * @param  array<string, string|int|float|bool|null>  $values
     *
     * @throws InvalidArgumentException
     */
    public function validate(array $values): void
    {
        foreach ($values as $key => $value) {
            if ($value === null) {
                continue;
            }

            $this->validateKey($key);
            $this->validateValue($key, (string) $value);
        }
    }

    /**
     * Validate that the key is in the allowed set.
     *
     *
     * @throws InvalidArgumentException
     */
    public function validateKey(string $key): void
    {
        if (! in_array($key, self::ALLOWED_KEYS, true)) {
            throw new InvalidArgumentException("Environment variable [{$key}] is not permitted for modification.");
        }
    }

    /**
     * Validate that the value contains no newline or null-byte characters.
     *
     *
     * @throws InvalidArgumentException
     */
    public function validateValue(string $key, string $value): void
    {
        if (preg_match('/[\r\n\0]/', $value)) {
            throw new InvalidArgumentException("Environment value for [{$key}] contains prohibited control or newline characters.");
        }
    }

    /**
     * Format and escape a value safely for dotenv.
     * Quotes the value in double quotes and escapes \, ", and $ characters.
     */
    public function formatValue(string $value): string
    {
        $escaped = str_replace(
            ['\\', '"', '$'],
            ['\\\\', '\\"', '\\$'],
            $value
        );

        return "\"{$escaped}\"";
    }
}
