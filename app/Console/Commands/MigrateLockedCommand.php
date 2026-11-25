<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class MigrateLockedCommand extends Command
{
    protected $signature = 'app:migrate-locked
        {--seed : Seed the database after migrations finish}
        {--database= : The database connection to migrate}
        {--lock-timeout=300 : Maximum seconds to wait for another replica to finish migrating}
        {--lock-ttl=600 : Maximum lock lifetime in seconds for cache-backed locks}';

    protected $description = 'Run database migrations behind a blocking lock so concurrent container boots never race.';

    public function handle(): int
    {
        $database = $this->option('database');
        $connectionName = is_string($database) && $database !== ''
            ? $database
            : (string) config('database.default');
        $lockTimeout = max(1, (int) $this->option('lock-timeout'));
        $lockTtl = max($lockTimeout, (int) $this->option('lock-ttl'));
        $shouldSeed = (bool) $this->option('seed');

        $migrateArguments = [
            '--force' => true,
            '--no-interaction' => true,
        ];

        if (is_string($database) && $database !== '') {
            $migrateArguments['--database'] = $database;
        }

        if ($shouldSeed) {
            $migrateArguments['--seed'] = true;
        }

        $runner = fn (): int => (int) $this->call('migrate', $migrateArguments);

        try {
            return $this->runWithLock($connectionName, $lockTimeout, $lockTtl, $runner);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Acquire a blocking cross-replica migration lock before invoking the runner.
     *
     * @param  callable(): int  $callback
     */
    private function runWithLock(string $connectionName, int $lockTimeout, int $lockTtl, callable $callback): int
    {
        $connection = DB::connection($connectionName);
        $driver = mb_strtolower($connection->getDriverName());

        if ($driver === 'pgsql') {
            return $this->runWithPostgresAdvisoryLock($connectionName, $lockTimeout, $callback);
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return $this->runWithMysqlLock($connectionName, $lockTimeout, $callback);
        }

        $cacheStore = (string) config('cache.default', 'file');
        $cacheDriver = (string) config("cache.stores.{$cacheStore}.driver", $cacheStore);

        if (in_array($cacheDriver, ['redis', 'memcached', 'dynamodb', 'array'], true)) {
            $store = Cache::store($cacheStore)->getStore();

            if ($store instanceof LockProvider) {
                return $this->runWithCacheLock($cacheStore, $connectionName, $lockTimeout, $lockTtl, $callback);
            }
        }

        return $this->runWithFileLock($connectionName, $lockTimeout, $callback);
    }

    /**
     * @param  callable(): int  $callback
     */
    private function runWithPostgresAdvisoryLock(string $connectionName, int $lockTimeout, callable $callback): int
    {
        $connection = DB::connection($connectionName);
        $databaseName = (string) $connection->getDatabaseName();
        $namespaceKey = $this->signedInt32('koakademy:migrations');
        $resourceKey = $this->signedInt32("{$connectionName}:{$databaseName}");

        $deadline = microtime(true) + $lockTimeout;
        $acquired = false;

        while (true) {
            $row = $connection->selectOne('SELECT pg_try_advisory_lock(?, ?) AS locked', [$namespaceKey, $resourceKey]);

            if ((bool) ($row->locked ?? false)) {
                $acquired = true;
                break;
            }

            if (microtime(true) >= $deadline) {
                throw new RuntimeException("Timed out after {$lockTimeout}s waiting for the PostgreSQL migration lock.");
            }

            usleep(250_000);
        }

        try {
            return $callback();
        } finally {
            if ($acquired) {
                $connection->selectOne('SELECT pg_advisory_unlock(?, ?)', [$namespaceKey, $resourceKey]);
            }
        }
    }

    /**
     * @param  callable(): int  $callback
     */
    private function runWithMysqlLock(string $connectionName, int $lockTimeout, callable $callback): int
    {
        $connection = DB::connection($connectionName);
        $lockName = 'koakademy_migrate_'.mb_substr(hash('sha256', "{$connectionName}:{$connection->getDatabaseName()}"), 0, 32);

        $row = $connection->selectOne('SELECT GET_LOCK(?, ?) AS locked', [$lockName, $lockTimeout]);

        if ((int) ($row->locked ?? 0) !== 1) {
            throw new RuntimeException("Timed out after {$lockTimeout}s waiting for the MySQL migration lock.");
        }

        try {
            return $callback();
        } finally {
            $connection->selectOne('SELECT RELEASE_LOCK(?)', [$lockName]);
        }
    }

    /**
     * @param  callable(): int  $callback
     */
    private function runWithCacheLock(
        string $cacheStore,
        string $connectionName,
        int $lockTimeout,
        int $lockTtl,
        callable $callback,
    ): int {
        $lockKey = "koakademy:migrations:{$connectionName}";

        try {
            $result = Cache::store($cacheStore)
                ->lock($lockKey, $lockTtl)
                ->block($lockTimeout, $callback);

            return is_int($result) ? $result : self::SUCCESS;
        } catch (LockTimeoutException) {
            throw new RuntimeException("Timed out after {$lockTimeout}s waiting for the cache migration lock ({$lockKey}).");
        }
    }

    /**
     * @param  callable(): int  $callback
     */
    private function runWithFileLock(string $connectionName, int $lockTimeout, callable $callback): int
    {
        $safeConnection = preg_replace('/[^A-Za-z0-9_.-]/', '_', $connectionName) ?: 'default';
        $lockDirectory = storage_path('framework');

        if (! is_dir($lockDirectory)) {
            mkdir($lockDirectory, 0775, true);
        }

        $lockPath = "{$lockDirectory}/migrate-{$safeConnection}.lock";
        $handle = fopen($lockPath, 'c+');

        if ($handle === false) {
            throw new RuntimeException("Unable to open migration lock file at {$lockPath}.");
        }

        $deadline = microtime(true) + $lockTimeout;
        $acquired = false;

        try {
            while (true) {
                if (flock($handle, LOCK_EX | LOCK_NB)) {
                    $acquired = true;
                    break;
                }

                if (microtime(true) >= $deadline) {
                    throw new RuntimeException("Timed out after {$lockTimeout}s waiting for the file migration lock ({$lockPath}).");
                }

                usleep(250_000);
            }

            return $callback();
        } finally {
            if ($acquired) {
                flock($handle, LOCK_UN);
            }

            fclose($handle);
        }
    }

    private function signedInt32(string $seed): int
    {
        $value = crc32($seed);

        if ($value >= 0x80000000) {
            return $value - 0x100000000;
        }

        return $value;
    }
}
