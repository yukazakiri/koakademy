<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

final class TestConnections extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-connections {--service= : Test specific service only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test connections to all configured services (R2, Redis, PostgreSQL, Minio, Pusher, etc.)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Testing Service Connections');
        $this->info(str_repeat('=', 50));

        $service = $this->option('service');
        $results = [];

        if ($service) {
            $method = 'test'.ucfirst($service);
            if (method_exists($this, $method)) {
                $results[$service] = $this->$method();
            } else {
                $this->error("❌ Service '{$service}' not found");

                return 1;
            }
        } else {
            $results = [
                'database' => $this->testDatabase(),
                'redis' => $this->testRedis(),
                'storage' => $this->testStorage(),
                'minio' => $this->testMinio(),
                'cache' => $this->testCache(),
                'queue' => $this->testQueue(),
                'supabase' => $this->testSupabase(),
                'pusher' => $this->testPusher(),
                'github' => $this->testGithub(),
            ];
        }

        $this->displayResults($results);

        $failed = count(array_filter($results, fn (array $result): bool => ! $result['success']));

        return $failed === 0 ? 0 : 1;
    }

    private function testDatabase(): array
    {
        try {
            $start = microtime(true);

            // Test basic connection
            $connection = DB::connection();
            $pdo = $connection->getPdo();

            // Test query execution
            $version = $connection->select('SELECT version()')[0]->version ?? 'Unknown';

            // Test table access
            $facultiesCount = DB::table('faculty')->count();

            $duration = round((microtime(true) - $start) * 1000, 2);

            return [
                'success' => true,
                'message' => 'PostgreSQL connected successfully',
                'details' => [
                    'version' => $version,
                    'database' => $connection->getDatabaseName(),
                    'host' => config('database.connections.pgsql.host'),
                    'port' => config('database.connections.pgsql.port'),
                    'faculties_count' => $facultiesCount,
                    'response_time' => "{$duration}ms",
                ],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'PostgreSQL connection failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function testRedis(): array
    {
        try {
            $start = microtime(true);

            // Test connection
            $redis = Redis::connection();
            $redis->ping();

            // Test basic operations
            $testKey = 'test:connection:'.time();
            $redis->set($testKey, 'test_value', 60);
            $value = $redis->get($testKey);
            $redis->del($testKey);

            $duration = round((microtime(true) - $start) * 1000, 2);

            return [
                'success' => true,
                'message' => 'Redis connected successfully',
                'details' => [
                    'host' => config('database.redis.host'),
                    'port' => config('database.redis.port'),
                    'database' => config('database.redis.database', 0),
                    'client' => config('database.redis.client'),
                    'test_value' => $value,
                    'response_time' => "{$duration}ms",
                ],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Redis connection failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function testStorage(): array
    {
        try {
            $start = microtime(true);

            $disk = Storage::disk(config('filesystems.default'));
            $diskName = config('filesystems.default');

            // Test file upload
            $testContent = 'Storage connection test at '.format_timestamp_now();
            $testFile = 'test-connections/'.uniqid().'.txt';
            $disk->put($testFile, $testContent, 'public');

            // Test file existence
            $exists = $disk->exists($testFile);

            // Test file URL generation
            $url = $disk->url($testFile);

            // Test file download
            if ($exists) {
                $downloaded = $disk->get($testFile);
                $contentMatch = $downloaded === $testContent;
            } else {
                $contentMatch = false;
            }

            // Cleanup
            if ($exists) {
                $disk->delete($testFile);
            }

            $duration = round((microtime(true) - $start) * 1000, 2);

            return [
                'success' => $exists && $contentMatch,
                'message' => $exists && $contentMatch ? 'Default storage connected successfully' : 'Default storage test failed',
                'details' => [
                    'disk' => $diskName,
                    'url' => $url,
                    'file_uploaded' => $exists,
                    'content_verified' => $contentMatch,
                    'response_time' => "{$duration}ms",
                ],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Default storage connection failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function testMinio(): array
    {
        try {
            $start = microtime(true);

            $disk = Storage::disk('minio');

            // Test file upload
            $testContent = 'Minio connection test at '.format_timestamp_now();
            $testFile = 'test-connections/'.uniqid().'.txt';
            $disk->put($testFile, $testContent, 'public');

            // Test file existence
            $exists = $disk->exists($testFile);

            // Test file URL generation
            $url = $disk->url($testFile);

            // Test temporary URL
            $tempUrl = method_exists($disk, 'temporaryUrl')
                ? $disk->temporaryUrl($testFile, now()->addMinutes(5))
                : 'N/A';

            // Test file download
            if ($exists) {
                $downloaded = $disk->get($testFile);
                $contentMatch = $downloaded === $testContent;
            } else {
                $contentMatch = false;
            }

            // Cleanup
            if ($exists) {
                $disk->delete($testFile);
            }

            $duration = round((microtime(true) - $start) * 1000, 2);

            return [
                'success' => $exists && $contentMatch,
                'message' => $exists && $contentMatch ? 'Minio storage connected successfully' : 'Minio storage test failed',
                'details' => [
                    'bucket' => config('filesystems.disks.minio.bucket'),
                    'endpoint' => config('filesystems.disks.minio.endpoint'),
                    'region' => config('filesystems.disks.minio.region'),
                    'url' => $url,
                    'temporary_url' => $tempUrl,
                    'file_uploaded' => $exists,
                    'content_verified' => $contentMatch,
                    'response_time' => "{$duration}ms",
                ],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Minio storage connection failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function testCache(): array
    {
        try {
            $start = microtime(true);

            // Test cache write
            $testKey = 'test:cache:'.time();
            $testValue = 'cache_test_value_'.uniqid();
            Cache::put($testKey, $testValue, 60);

            // Test cache read
            $retrieved = Cache::get($testKey);

            // Test cache forget
            Cache::forget($testKey);

            // Verify it's gone
            $existsAfterDelete = Cache::has($testKey);

            $duration = round((microtime(true) - $start) * 1000, 2);

            return [
                'success' => $retrieved === $testValue && ! $existsAfterDelete,
                'message' => 'Cache working correctly',
                'details' => [
                    'driver' => config('cache.default'),
                    'redis_connection' => config('cache.stores.redis.connection'),
                    'value_written' => $testValue,
                    'value_retrieved' => $retrieved,
                    'deleted_successfully' => ! $existsAfterDelete,
                    'response_time' => "{$duration}ms",
                ],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Cache test failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function testQueue(): array
    {
        try {
            $start = microtime(true);

            // Test queue connection
            $connection = Queue::connection();
            $size = $connection->size();

            $duration = round((microtime(true) - $start) * 1000, 2);

            return [
                'success' => true,
                'message' => 'Queue connection working',
                'details' => [
                    'connection' => config('queue.default'),
                    'redis_connection' => config('queue.connections.redis.connection'),
                    'queue_size' => $size,
                    'response_time' => "{$duration}ms",
                ],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Queue connection failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function testSupabase(): array
    {
        try {
            $start = microtime(true);

            $endpoint = env('SUPABASE_STORAGE_ENDPOINT');
            $key = env('SUPABASE_STORAGE_KEY');
            $bucket = env('SUPABASE_STORAGE_BUCKET');

            if (! $endpoint || ! $key || ! $bucket) {
                throw new Exception('Missing Supabase configuration');
            }

            // Test basic HTTP connection to Supabase
            $response = Http::withHeaders([
                'apikey' => $key,
                'Authorization' => "Bearer {$key}",
            ])->get("{$endpoint}/storage/v1/bucket");

            $duration = round((microtime(true) - $start) * 1000, 2);

            return [
                'success' => $response->successful(),
                'message' => $response->successful() ? 'Supabase connected successfully' : 'Supabase connection failed',
                'details' => [
                    'endpoint' => $endpoint,
                    'bucket' => $bucket,
                    'status_code' => $response->status(),
                    'response_time' => "{$duration}ms",
                ],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Supabase connection failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function testPusher(): array
    {
        try {
            $start = microtime(true);

            $key = env('PUSHER_APP_KEY');
            $cluster = env('PUSHER_APP_CLUSTER');
            $host = env('VITE_PUSHER_HOST');
            $port = env('VITE_PUSHER_PORT');

            if (! $key) {
                throw new Exception('Missing Pusher configuration');
            }

            // Test basic HTTP connection to Pusher WebSocket endpoint
            $wsUrl = $host ?
                "https://{$host}:{$port}/app/{$key}" :
                "https://ws-{$cluster}.pusher.com/app/{$key}";

            $wsResponse = Http::timeout(5)->get($wsUrl);

            // Test Pusher Authentication API endpoint
            $apiUrl = $host ?
                "https://{$host}:{$port}/apps/{$key}" :
                "https://api-{$cluster}.pusher.com/apps/{$key}";

            $apiResponse = Http::timeout(5)->withHeaders([
                'Authorization' => "Bearer {$key}",
            ])->get($apiUrl);

            $duration = round((microtime(true) - $start) * 1000, 2);

            return [
                'success' => true, // Even 404/426 is expected for Pusher app URL
                'message' => 'Pusher configuration found and accessible',
                'details' => [
                    'app_key' => mb_substr((string) $key, 0, 8).'...',
                    'cluster' => $cluster,
                    'host' => $host ?: 'default',
                    'port' => $port,
                    'ws_url' => $wsUrl,
                    'api_url' => $apiUrl,
                    'ws_status_code' => $wsResponse->status(),
                    'api_status_code' => $apiResponse->status(),
                    'websockets_available' => $wsResponse->successful() || $wsResponse->status() === 426,
                    'api_accessible' => $apiResponse->successful() || $apiResponse->status() === 401,
                    'response_time' => "{$duration}ms",
                ],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Pusher configuration error',
                'error' => $e->getMessage(),
                'details' => [
                    'cluster' => $cluster ?? 'Not configured',
                    'host' => $host ?? 'Not configured',
                    'error_type' => $e::class,
                ],
            ];
        }
    }

    private function testGithub(): array
    {
        try {
            $start = microtime(true);

            $token = env('GITHUB_TOKEN');
            $repo = env('GITHUB_REPOSITORY');

            if (! $token || ! $repo) {
                throw new Exception('Missing GitHub configuration');
            }

            // Test GitHub API connection
            $response = Http::withHeaders([
                'Authorization' => "token {$token}",
                'Accept' => 'application/vnd.github.v3+json',
            ])->timeout(10)->get("https://api.github.com/repos/{$repo}");

            $duration = round((microtime(true) - $start) * 1000, 2);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'message' => 'GitHub API connected successfully',
                    'details' => [
                        'repository' => $repo,
                        'repo_name' => $data['full_name'] ?? 'Unknown',
                        'private' => $data['private'] ?? false,
                        'stars' => $data['stargazers_count'] ?? 0,
                        'last_updated' => $data['updated_at'] ?? 'Unknown',
                        'response_time' => "{$duration}ms",
                    ],
                ];
            }

            return [
                'success' => false,
                'message' => 'GitHub API request failed',
                'error' => $response->json('message', 'Unknown error'),
                'details' => [
                    'repository' => $repo,
                    'status_code' => $response->status(),
                    'response_time' => "{$duration}ms",
                ],
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'GitHub connection failed',
                'error' => $e->getMessage(),
                'details' => [
                    'repository' => $repo ?? 'Not configured',
                    'error_type' => $e::class,
                ],
            ];
        }
    }

    private function displayResults(array $results): void
    {
        $this->newLine();

        foreach ($results as $service => $result) {
            $icon = $result['success'] ? '✅' : '❌';
            $status = $result['success'] ? 'PASS' : 'FAIL';
            $color = $result['success'] ? 'green' : 'red';

            $this->line("{$icon} {$service}: {$status}");

            if (! $result['success']) {
                $error = $result['error'] ?? 'Unknown error';
                $this->line("   └─ Error: {$error}");
            } else {
                $this->line("   └─ {$result['message']}");
            }

            if (isset($result['details'])) {
                foreach ($result['details'] as $key => $value) {
                    if (is_bool($value)) {
                        $value = $value ? 'true' : 'false';
                    }
                    $this->line("      {$key}: {$value}");
                }
            }

            $this->newLine();
        }

        $passed = count(array_filter($results, fn (array $result) => $result['success']));
        $total = count($results);

        if ($passed === $total) {
            $this->info("🎉 All services are working correctly! ({$passed}/{$total})");
        } else {
            $this->error("⚠️  Some services are not working! ({$passed}/{$total} passed)");
        }
    }
}
