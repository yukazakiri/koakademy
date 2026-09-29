<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Resolves the SQLite file a test process may use.
 *
 * Parallel workers previously shared one testing.sqlite. RefreshDatabase runs
 * `migrate:fresh` on a file-backed database, so each worker's first test dropped
 * and rebuilt the tables while the other workers were mid test. That surfaced
 * as spurious "expected N rows, found 0" failures in whichever report or
 * aggregate happened to be running at the time, which is why adding unrelated
 * test files could turn a green suite red.
 *
 * Each worker therefore gets its own file. The token is the same value
 * ParallelTesting::token() reads, taken from the environment because this runs
 * before the application exists; it is absent outside parallel runs, so a
 * plain `php artisan test` still uses the single default file.
 */
final class TestingDatabase
{
    public static function path(): string
    {
        $token = (string) ($_SERVER['TEST_TOKEN'] ?? '');

        $name = $token !== ''
            ? "testing_{$token}.sqlite"
            : 'testing.sqlite';

        return dirname(__DIR__, 2)."/database/{$name}";
    }

    /**
     * Ensure the file exists and return its resolved absolute path.
     */
    public static function ensureExists(): string
    {
        $path = self::path();

        if (! file_exists($path)) {
            touch($path);
        }

        return realpath($path) ?: $path;
    }
}
