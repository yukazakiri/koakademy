<?php

declare(strict_types=1);

use Laravel\Pulse\Recorders;

/**
 * Read the default of an env() call from a config file.
 *
 * The application config is resolved once, at boot, so a variable that is
 * missing from the environment falls back to the default written in the
 * file. Reading that default out of the source is the only way to prove
 * what a deployment that omits the variable ends up with.
 */
function runtimeEnvDefault(string $file, string $variable): string
{
    $source = (string) file_get_contents(dirname(__DIR__, 2)."/config/{$file}");

    $pattern = "/env\(\s*'".preg_quote($variable, '/')."'\s*,\s*([^)]+?)\s*\)/";

    expect($source)->toMatch($pattern, "{$file} must read {$variable} from the environment.");

    preg_match($pattern, $source, $matches);

    return mb_trim($matches[1]);
}

/**
 * The phpredis default for both timeouts is 0, which waits forever. An
 * unreachable Redis then hangs the worker instead of failing the request,
 * so every connection has to carry a finite bound.
 */
it('bounds the connect and read timeouts of every redis connection', function (string $connection): void {
    $redis = config('database.redis');

    expect($redis)->toHaveKey($connection);

    $timeout = $redis[$connection]['timeout'] ?? null;
    $readTimeout = $redis[$connection]['read_timeout'] ?? null;

    expect($timeout)->toBeFloat()
        ->and($timeout)->toBeGreaterThan(0)
        ->and($readTimeout)->toBeFloat()
        ->and($readTimeout)->toBeGreaterThan(0);
})->with(['default', 'cache', 'queue-pdf', 'queue-assessment', 'pulse']);

/**
 * The queue worker blocks on BLPOP for `block_for` seconds. A read timeout
 * at or below that value kills the worker with a socket timeout instead of
 * simply reporting an empty queue.
 */
it('keeps the redis read timeout above the queue block time', function (): void {
    $redis = config('database.redis');
    $queues = config('queue.connections');

    $blockFor = collect($queues)
        ->filter(fn (array $connection): bool => ($connection['driver'] ?? null) === 'redis')
        ->map(fn (array $connection): int => (int) ($connection['block_for'] ?? 0))
        ->max() ?? 0;

    foreach (['default', 'cache', 'queue-pdf', 'queue-assessment', 'pulse'] as $connection) {
        expect($redis[$connection]['read_timeout'])->toBeGreaterThan($blockFor);
    }
});

it('reads the redis timeouts from the environment with a bounded default', function (): void {
    expect(runtimeEnvDefault('database.php', 'REDIS_TIMEOUT'))->toBe('3')
        ->and(runtimeEnvDefault('database.php', 'REDIS_READ_TIMEOUT'))->toBe('10');
});

/**
 * Predis reads the socket timeouts from the connection parameters and calls
 * the read timeout "read_write_timeout", so it needs its own copy of the
 * bound or it keeps waiting forever.
 */
it('bounds the read timeout for the predis client as well', function (): void {
    $readWriteTimeout = config('database.redis.options.parameters.read_write_timeout');

    expect($readWriteTimeout)->toBeFloat()
        ->and($readWriteTimeout)->toBeGreaterThan(0);
});

/**
 * A shared Meilisearch instance must not let one installation overwrite
 * another installation's indexes.
 */
it('namespaces the search index', function (): void {
    $prefix = config('scout.prefix');

    expect($prefix)->not->toBe('')
        ->and($prefix)->toContain('scout');
});

/**
 * Indexing a record used to be a blocking HTTP call to the search engine
 * inside the request that saved the record.
 */
it('queues the search indexing on a dedicated queue', function (): void {
    $scout = config('scout');

    expect($scout['queue'])->toBeArray()
        ->and($scout['queue']['queue'])->toBe('scout');
});

/**
 * A job that is pushed while the transaction is still open can describe a
 * row the transaction later rolls back.
 */
it('dispatches the queued jobs after the transaction commits', function (): void {
    $queues = config('queue.connections');

    expect(config('scout.after_commit'))->toBeTrue()
        ->and($queues['redis']['after_commit'])->toBeTrue()
        ->and($queues['database']['after_commit'])->toBeTrue();
});

it('gives horizon a worker on the search index queue', function (): void {
    expect(config('horizon.defaults.supervisor-1.queue'))->toContain('scout');
});

/**
 * Pulse and Telescope record into the database during the request they
 * observe, so an installation that never asked for them must not pay for
 * them.
 */
it('leaves the observability recorders switched off by default', function (): void {
    expect(runtimeEnvDefault('pulse.php', 'PULSE_ENABLED'))->toBe('false')
        ->and(runtimeEnvDefault('telescope.php', 'TELESCOPE_ENABLED'))->toBe('false');
});

/**
 * A recorder that fires on every request produces an event per request,
 * and the ingest driver writes those events during the request.
 */
it('samples the high volume pulse recorders', function (string $recorder): void {
    $recorders = config('pulse.recorders');

    expect($recorders[$recorder]['sample_rate'])->toBeLessThan(1);
})->with([
    'cache interactions' => [Recorders\CacheInteractions::class],
    'user requests' => [Recorders\UserRequests::class],
    'slow queries' => [Recorders\SlowQueries::class],
    'slow requests' => [Recorders\SlowRequests::class],
]);
