<?php

declare(strict_types=1);

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => (env('APP_ENV') === 'testing' || (defined('PHPUNIT_COMPOSER_INSTALL') || class_exists(PHPUnit\Framework\TestCase::class, false)))
                ? (env('DB_DATABASE') && ! str_ends_with(str_replace('\\', '/', (string) env('DB_DATABASE')), 'database.sqlite')
                    ? env('DB_DATABASE')
                    : database_path('testing.sqlite'))
                : env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                Pdo\Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                Pdo\Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
            // Keep timezone as UTC to preserve existing data integrity
            // PHP handles timezone conversion via shiftTimezone() in HandleInertiaRequests
            'timezone' => 'UTC',
        ],

        'pulse' => [
            'driver' => env('PULSE_DB_DRIVER', env('DB_CONNECTION', 'sqlite')),
            'url' => env('PULSE_DB_URL'),
            'host' => env('PULSE_DB_HOST', env('DB_HOST', 'pgsql')),
            'port' => env('PULSE_DB_PORT', env('DB_PORT', '5432')),
            'database' => (env('APP_ENV') === 'testing' || (defined('PHPUNIT_COMPOSER_INSTALL') || class_exists(PHPUnit\Framework\TestCase::class, false)))
                ? database_path('testing.sqlite')
                : env(
                    'PULSE_DB_DATABASE',
                    // Reuse the application database unless an operator explicitly
                    // provisions a dedicated database for Pulse.
                    env('DB_DATABASE', database_path('database.sqlite'))
                ),
            'username' => env('PULSE_DB_USERNAME', env('DB_USERNAME', 'root')),
            'password' => env('PULSE_DB_PASSWORD', env('DB_PASSWORD', '')),
            'charset' => env('PULSE_DB_CHARSET', env('DB_CHARSET', 'utf8')),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => env('PULSE_DB_SCHEMA', 'public'),
            'sslmode' => env('PULSE_DB_SSLMODE', 'prefer'),
            'timezone' => 'UTC',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    | Every connection below sets a connect timeout and a read timeout. The
    | phpredis default for both is 0, which means "wait forever". That turns
    | an unreachable Redis into a hung request: an Octane worker sits on the
    | first cache read and never returns, so the pool drains into a full
    | outage instead of a degraded service. A bounded timeout fails fast and
    | lets the worker serve a cached or default response.
    |
    | The read timeout must stay above the queue `block_for` values (5s by
    | default). The queue worker blocks on BLPOP for that long, and a read
    | timeout at or below it would abort the worker with a socket timeout
    | instead of simply returning an empty queue.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),

            // Predis reads its socket timeouts from the connection parameters
            // and calls the read timeout "read_write_timeout". phpredis ignores
            // this block and reads "timeout" and "read_timeout" from the
            // connection below, so both clients end up with the same bound.
            'parameters' => [
                'read_write_timeout' => (float) env('REDIS_READ_TIMEOUT', 10),
            ],
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'timeout' => (float) env('REDIS_TIMEOUT', 3),
            'read_timeout' => (float) env('REDIS_READ_TIMEOUT', 10),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'timeout' => (float) env('REDIS_TIMEOUT', 3),
            'read_timeout' => (float) env('REDIS_READ_TIMEOUT', 10),
        ],

        'queue-pdf' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_PDF_QUEUE_DB', '3'),
            'timeout' => (float) env('REDIS_TIMEOUT', 3),
            'read_timeout' => (float) env('REDIS_READ_TIMEOUT', 10),
        ],

        'queue-assessment' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_ASSESSMENT_QUEUE_DB', '5'),
            'timeout' => (float) env('REDIS_TIMEOUT', 3),
            'read_timeout' => (float) env('REDIS_READ_TIMEOUT', 10),
        ],

        'pulse' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_PULSE_DB', '4'),
            'timeout' => (float) env('REDIS_TIMEOUT', 3),
            'read_timeout' => (float) env('REDIS_READ_TIMEOUT', 10),
        ],

    ],

];
