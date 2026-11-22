<?php

declare(strict_types=1);

use App\Models\GeneralSetting;
use App\Modules\CompatibilityChecker;
use App\Modules\ModuleManifestRepository;
use App\Modules\ModuleStateRepository;
use App\Services\FeatureAccessService;
use App\Services\GeneralSettingsService;
use App\Services\TenantContext;
use App\Support\Octane\DisconnectSqliteConnections;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Octane\Contracts\OperationTerminated;
use Laravel\Octane\Events\RequestTerminated;
use Laravel\Octane\Events\WorkerErrorOccurred;
use Laravel\Octane\Events\WorkerStarting;
use Mockery\MockInterface;
use Modules\Announcement\Models\Announcement;
use Symfony\Component\HttpFoundation\Response;

it('resets request-scoped state between Octane requests', function (string $abstract): void {
    $first = app($abstract);

    app()->forgetScopedInstances();

    expect(app($abstract))->not->toBe($first);
})->with([
    GeneralSettingsService::class,
    TenantContext::class,
    FeatureAccessService::class,
    ModuleStateRepository::class,
    ModuleManifestRepository::class,
    CompatibilityChecker::class,
]);

it('keeps request state out of static properties', function (string $class): void {
    $staticProperties = array_filter(
        (new ReflectionClass($class))->getProperties(ReflectionProperty::IS_STATIC),
        static fn (ReflectionProperty $property): bool => $property->getDeclaringClass()->getName() === $class,
    );

    expect($staticProperties)->toBe([]);
})->with([
    GeneralSettingsService::class,
    TenantContext::class,
    FeatureAccessService::class,
    ModuleStateRepository::class,
    ModuleManifestRepository::class,
    Announcement::class,
]);

it('reloads global settings when another worker changes them', function (): void {
    GeneralSetting::factory()->create(['site_name' => 'Old value']);

    $workerOne = app(GeneralSettingsService::class);

    expect($workerOne->getGlobalSetting('site_name'))->toBe('Old value');

    // Simulate another worker updating the row and invalidating the shared cache
    // without touching this worker's process-local state.
    DB::table('general_settings')->update(['site_name' => 'New value']);
    Cache::forget('general_settings_id');

    app()->forgetScopedInstances();

    $workerTwo = app(GeneralSettingsService::class);

    expect($workerTwo->getGlobalSetting('site_name'))->toBe('New value');
});

it('reads announcement schema columns after the shared cache is refreshed', function (): void {
    Cache::put('announcement_schema_columns', []);

    expect(Announcement::schemaHasCachedColumn('id'))->toBeFalse();

    Cache::put('announcement_schema_columns', ['id']);

    expect(Announcement::schemaHasCachedColumn('id'))->toBeTrue();
});

it('flushes request-state bindings before the next Octane request', function (): void {
    expect(config('octane.flush'))
        ->toContain(GeneralSettingsService::class)
        ->toContain(TenantContext::class)
        ->toContain(FeatureAccessService::class)
        ->toContain(ModuleStateRepository::class)
        ->toContain(ModuleManifestRepository::class);
});

it('registers DisconnectSqliteConnections for Octane worker lifecycle events', function (): void {
    expect(config('octane.listeners.'.WorkerStarting::class))
        ->toContain(DisconnectSqliteConnections::class);
    expect(config('octane.listeners.'.OperationTerminated::class))
        ->toContain(DisconnectSqliteConnections::class);
    expect(config('octane.listeners.'.WorkerErrorOccurred::class))
        ->toContain(DisconnectSqliteConnections::class);
});

it('disconnects file-backed SQLite connections across Octane events and recovers after file replacement', function (): void {
    $path = sys_get_temp_dir().'/koakademy_octane_'.Str::random(16).'.sqlite';
    $connectionName = 'sqlite_octane_file_test';

    $initialPdo = new PDO('sqlite:'.$path);
    $initialPdo->exec('CREATE TABLE sessions (id TEXT PRIMARY KEY)');
    $initialPdo->exec("INSERT INTO sessions (id) VALUES ('stale-session')");
    $initialPdo = null;

    config([
        "database.connections.{$connectionName}" => [
            'driver' => 'sqlite',
            'database' => $path,
            'prefix' => '',
        ],
    ]);

    try {
        $connection = DB::connection($connectionName);

        expect($connection)->toBeInstanceOf(SQLiteConnection::class);

        $handler = app(DisconnectSqliteConnections::class);
        $events = [
            new RequestTerminated(app(), app(), Request::create('/'), new Response),
            new WorkerStarting(app()),
            new WorkerErrorOccurred(new RuntimeException('Simulated worker failure'), app()),
        ];

        foreach ($events as $event) {
            expect($connection->table('sessions')->value('id'))->toBe('stale-session');
            expect($connection->getRawPdo())->not->toBeNull();

            $handler->handle($event);

            expect($connection->getRawPdo())->toBeNull();
        }

        unlink($path);

        $freshPdo = new PDO('sqlite:'.$path);
        $freshPdo->exec('CREATE TABLE sessions (id TEXT PRIMARY KEY)');
        $freshPdo->exec("INSERT INTO sessions (id) VALUES ('fresh-session')");
        $freshPdo = null;

        expect($connection->table('sessions')->value('id'))->toBe('fresh-session');
    } finally {
        DB::purge($connectionName);

        if (file_exists($path)) {
            unlink($path);
        }
    }
});

it('keeps in-memory SQLite connections connected across Octane events', function (): void {
    $connectionName = 'sqlite_octane_memory_test';

    config([
        "database.connections.{$connectionName}" => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ],
    ]);

    try {
        $connection = DB::connection($connectionName);
        $connection->statement('CREATE TABLE sessions (id TEXT PRIMARY KEY)');
        $connection->table('sessions')->insert(['id' => 'memory-session']);

        app(DisconnectSqliteConnections::class)->handle(
            new RequestTerminated(app(), app(), Request::create('/'), new Response),
        );

        expect($connection->getRawPdo())->not->toBeNull();
        expect($connection->table('sessions')->value('id'))->toBe('memory-session');
    } finally {
        DB::purge($connectionName);
    }
});

it('leaves non-SQLite database connections untouched', function (): void {
    $connectionName = 'pgsql_octane_test';
    $mock = Mockery::mock(PostgresConnection::class);
    $mock->makePartial();

    $disconnectCalls = 0;
    $mock->shouldReceive('disconnect')->andReturnUsing(function () use (&$disconnectCalls): void {
        $disconnectCalls++;
    });

    config([
        "database.connections.{$connectionName}" => [
            'driver' => 'pgsql',
            'database' => 'koakademy',
        ],
    ]);

    DB::extend($connectionName, static fn (): MockInterface => $mock);

    try {
        $resolved = DB::connection($connectionName);

        expect($resolved)->toBe($mock);

        app(DisconnectSqliteConnections::class)->handle(
            new RequestTerminated(app(), app(), Request::create('/'), new Response),
        );

        expect($disconnectCalls)->toBe(0);
    } finally {
        DB::purge($connectionName);
    }
});
