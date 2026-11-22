<?php

declare(strict_types=1);

namespace App\Support\Octane;

use Illuminate\Database\SQLiteConnection;
use Illuminate\Foundation\Application;
use Laravel\Octane\Contracts\OperationTerminated;
use Laravel\Octane\Events\WorkerErrorOccurred;
use Laravel\Octane\Events\WorkerStarting;

/**
 * Close file-backed SQLite connections after Octane boot, requests, and errors
 * so workers do not remain pinned to deleted or truncated database files.
 */
final class DisconnectSqliteConnections
{
    /**
     * Disconnect any open file-backed SQLite connections on the active container.
     */
    public function handle(WorkerStarting|OperationTerminated|WorkerErrorOccurred $event): void
    {
        $app = $this->resolveApplication($event);

        if (! $app->resolved('db')) {
            return;
        }

        foreach ($app->make('db')->getConnections() as $connection) {
            if (! $connection instanceof SQLiteConnection) {
                continue;
            }

            if ($this->isInMemoryDatabase($connection->getDatabaseName())) {
                continue;
            }

            $connection->disconnect();
        }
    }

    private function resolveApplication(WorkerStarting|OperationTerminated|WorkerErrorOccurred $event): Application
    {
        if ($event instanceof OperationTerminated) {
            return $event->sandbox();
        }

        if ($event instanceof WorkerErrorOccurred) {
            return $event->sandbox;
        }

        return $event->app;
    }

    private function isInMemoryDatabase(string $database): bool
    {
        return $database === ':memory:'
            || str_contains($database, '?mode=memory')
            || str_contains($database, '&mode=memory');
    }
}
