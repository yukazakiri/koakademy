<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redis;

final class RedisMonitor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:monitor
                            {--connections=* : The Redis connections to monitor}
                            {--max-memory= : The maximum memory usage in megabytes}
                            {--max-connections= : The maximum number of connected clients}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor Redis connections to check if they are online or failed';

    /**
     * Execute the console command.
     */
    public function handle(Dispatcher $events): int
    {
        $connections = $this->option('connections');

        if (empty($connections)) {
            $connections = array_keys(config('database.redis'));
            $connections = array_filter($connections, fn (int|string $name): bool => $name !== 'client' && $name !== 'options');
        }

        $this->displayHeader($connections);

        $results = $this->checkConnections($connections);

        $failed = array_filter($results, fn (array $result): bool => $result['status'] === 'failed');
        $warnings = array_filter($results, fn (array $result): bool => $result['status'] === 'warning');

        $this->displayResults($results);

        if (count($failed) > 0) {
            $this->dispatchEvents($events, $failed);

            return 1;
        }

        if (count($warnings) > 0) {
            return 1;
        }

        $this->newLine();
        $this->components->info('All Redis connections are online and healthy.');

        return 0;
    }

    /**
     * Display the header information.
     */
    private function displayHeader(array $connections): void
    {
        $this->newLine();

        $this->components->twoColumnDetail(
            '<fg=gray>Monitoring Redis connections</>',
            Arr::join($connections, ', ', ' and ')
        );

        if (($memory = $this->option('max-memory')) !== null) {
            $this->components->twoColumnDetail(
                '<fg=gray>Max memory threshold</>',
                "<fg=yellow>{$memory}</> <fg=gray>MB</>"
            );
        }

        if (($maxConnections = $this->option('max-connections')) !== null) {
            $this->components->twoColumnDetail(
                '<fg=gray>Max connections threshold</>',
                "<fg=yellow>{$maxConnections}</> <fg=gray>clients</>"
            );
        }

        $this->newLine();
    }

    /**
     * Check all Redis connections.
     */
    private function checkConnections(array $connections): array
    {
        $results = [];

        foreach ($connections as $connection) {
            try {
                $redis = Redis::connection($connection);

                // Test connection with ping
                $pingResponse = $redis->ping();

                // Handle different ping response types (phpredis vs predis)
                $pingSuccess = in_array($pingResponse, [true, 'PONG', '+PONG'], true)
                    || (is_object($pingResponse) && method_exists($pingResponse, 'getPayload') && $pingResponse->getPayload() === 'PONG')
                    || (is_object($pingResponse) && (string) $pingResponse === 'PONG');

                if (! $pingSuccess) {
                    $results[$connection] = [
                        'status' => 'failed',
                        'message' => 'Redis ping failed',
                        'error' => 'Unexpected ping response: '.var_export($pingResponse, true),
                    ];

                    continue;
                }

                // Get Redis INFO - returns different sections
                $memoryInfo = $redis->info('memory');
                $clientsInfo = $redis->info('clients');
                $serverInfo = $redis->info('server');
                $statsInfo = $redis->info('stats');
                $info = array_merge($memoryInfo, $clientsInfo, $serverInfo, $statsInfo);

                // Parse memory usage (in bytes, convert to MB)
                $memoryUsageMb = round((int) ($info['used_memory'] ?? 0) / 1024 / 1024, 2);

                // Get connected clients
                $connectedClients = (int) ($info['connected_clients'] ?? 0);

                $warnings = [];

                // Check memory threshold
                if ($maxMemory = $this->option('max-memory') !== null && $memoryUsageMb >= (float) $maxMemory) {
                    $warnings[] = "Memory usage ({$memoryUsageMb} MB) exceeds threshold ({$maxMemory} MB)";
                }

                // Check connections threshold
                if ($maxConnections = $this->option('max-connections') !== null && $connectedClients >= (int) $maxConnections) {
                    $warnings[] = "Connected clients ({$connectedClients}) exceeds threshold ({$maxConnections})";
                }

                $results[$connection] = [
                    'status' => count($warnings) > 0 ? 'warning' : 'online',
                    'message' => count($warnings) > 0 ? 'Online with warnings' : 'Online',
                    'info' => $info,
                    'memory_mb' => $memoryUsageMb,
                    'clients' => $connectedClients,
                    'warnings' => $warnings,
                ];
            } catch (Exception $e) {
                $results[$connection] = [
                    'status' => 'failed',
                    'message' => 'Connection failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Display the results of the connection checks.
     */
    private function displayResults(array $results): void
    {
        foreach ($results as $connection => $result) {
            $icon = match ($result['status']) {
                'online' => '✅',
                'warning' => '⚠️',
                'failed' => '❌',
                default => '❓',
            };

            $statusColor = match ($result['status']) {
                'online' => 'green',
                'warning' => 'yellow',
                'failed' => 'red',
                default => 'gray',
            };

            $this->components->twoColumnDetail(
                "<fg=gray>{$connection}</>",
                "{$icon} <fg={$statusColor};options=bold>{$result['message']}</>"
            );

            // Display error if failed
            if ($result['status'] === 'failed') {
                $this->components->bulletList([
                    '<fg=red>Error:</> '.$result['error'],
                ]);
                $this->newLine();

                continue;
            }

            // Display info for online connections
            if (isset($result['info'])) {
                $details = [
                    'Version: '.($result['info']['redis_version'] ?? 'Unknown'),
                    'Memory: '.$result['memory_mb'].' MB',
                    'Clients: '.$result['clients'],
                    'Uptime: '.($result['info']['uptime_in_days'] ?? 'Unknown').' days',
                ];

                $this->components->bulletList($details);
            }

            // Display warnings if any
            if (! empty($result['warnings'])) {
                $this->newLine();
                $this->line('  <fg=yellow>Warnings:</>');

                foreach ($result['warnings'] as $warning) {
                    $this->components->bulletList(['<fg=yellow>'.$warning.'</>']);
                }
            }

            $this->newLine();
        }
    }

    /**
     * Dispatch the Redis monitor events.
     */
    private function dispatchEvents(Dispatcher $events, array $failed): void
    {
        unset($events, $failed);
        // You can dispatch custom events here if needed
        // For example: RedisConnectionFailed event
        // foreach ($failed as $connection => $data) {
        //     $events->dispatch(new RedisConnectionFailed($connection, $data));
        // }
    }
}
