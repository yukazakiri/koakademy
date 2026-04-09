<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\BrowsershotService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use Throwable;

final class TestBrowsershotEnvironmentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;

    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(private string $testId)
    {
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting browsershot environment test job', [
            'test_id' => $this->testId,
            'process_id' => getmypid(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
        ]);

        try {
            // Test 1: Environment Variables
            $this->testEnvironmentVariables();

            // Test 2: Path Detection
            $this->testPathDetection();

            // Test 3: File Accessibility
            $this->testFileAccessibility();

            // Test 4: PDF Generation
            $this->testPdfGeneration();

            Log::info('Browsershot environment test job completed successfully', [
                'test_id' => $this->testId,
            ]);

        } catch (Exception $exception) {
            Log::error('Browsershot environment test job failed', [
                'test_id' => $this->testId,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
            throw $exception;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $throwable): void
    {
        Log::error('Browsershot environment test job failed permanently', [
            'test_id' => $this->testId,
            'error' => $throwable->getMessage(),
            'trace' => $throwable->getTraceAsString(),
        ]);
    }

    /**
     * Test environment variables in queue worker
     */
    private function testEnvironmentVariables(): void
    {
        $envVars = [
            'CHROME_PATH',
            'NODE_BINARY_PATH',
            'NPM_BINARY_PATH',
            'BROWSERSHOT_NO_SANDBOX',
            'BROWSERSHOT_TIMEOUT',
            'BROWSERSHOT_TEMP_DIRECTORY',
            'QUEUE_CONNECTION',
            'APP_ENV',
            'APP_DEBUG',
        ];

        $results = [];
        foreach ($envVars as $envVar) {
            $value = env($envVar);
            $results[$envVar] = $value ?: 'NOT_SET';
        }

        Log::info('Queue worker environment variables', [
            'test_id' => $this->testId,
            'environment_variables' => $results,
            'php_version' => PHP_VERSION,
            'current_working_directory' => getcwd(),
            'user' => get_current_user(),
        ]);
    }

    /**
     * Test BrowsershotService path detection in queue worker
     */
    private function testPathDetection(): void
    {
        try {
            $config = config('browsershot', []);

            // Use reflection to access private methods
            $reflectionClass = new ReflectionClass(BrowsershotService::class);

            $detectChrome = $reflectionClass->getMethod('detectChromePath');
            $chromePath = $detectChrome->invoke(null, $config);

            $detectNode = $reflectionClass->getMethod('detectNodePath');
            $nodePath = $detectNode->invoke(null, $config);

            $detectNpm = $reflectionClass->getMethod('detectNpmPath');
            $npmPath = $detectNpm->invoke(null, $config);

            Log::info('Queue worker path detection', [
                'test_id' => $this->testId,
                'chrome_path_detected' => $chromePath ?: 'NOT_DETECTED',
                'node_path_detected' => $nodePath ?: 'NOT_DETECTED',
                'npm_path_detected' => $npmPath ?: 'NOT_DETECTED',
                'config_values' => [
                    'chrome_path_config' => $config['chrome_path'] ?? 'NOT_SET',
                    'node_binary_path_config' => $config['node_binary_path'] ?? 'NOT_SET',
                    'npm_binary_path_config' => $config['npm_binary_path'] ?? 'NOT_SET',
                ],
            ]);

        } catch (Exception $exception) {
            Log::error('Queue worker path detection failed', [
                'test_id' => $this->testId,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }

    /**
     * Test file accessibility in queue worker
     */
    private function testFileAccessibility(): void
    {
        $paths = [
            'chromium' => [
                '/root/.nix-profile/bin/chromium',
                '/root/.nix-profile/bin/chromium-browser',
                '/nix/var/nix/profiles/default/bin/chromium',
                '/nix/var/nix/profiles/default/bin/chromium-browser',
                '/sbin/chromium',
                '/usr/bin/chromium',
                '/usr/bin/chromium-browser',
            ],
            'node' => [
                '/root/.nix-profile/bin/node',
                '/nix/var/nix/profiles/default/bin/node',
                '/sbin/node',
                '/usr/bin/node',
            ],
            'npm' => [
                '/root/.nix-profile/bin/npm',
                '/nix/var/nix/profiles/default/bin/npm',
                '/sbin/npm',
                '/usr/bin/npm',
            ],
        ];

        $results = [];
        foreach ($paths as $binary => $pathList) {
            $results[$binary] = [];
            foreach ($pathList as $path) {
                $exists = file_exists($path);
                $executable = $exists && is_executable($path);
                $readable = $exists && is_readable($path);

                $results[$binary][$path] = [
                    'exists' => $exists,
                    'executable' => $executable,
                    'readable' => $readable,
                ];

                if ($executable) {
                    // Try to get version info
                    try {
                        $version = null;
                        if ($binary === 'chromium') {
                            $version = shell_exec($path.' --version 2>/dev/null');
                        } elseif ($binary === 'node') {
                            $version = shell_exec($path.' --version 2>/dev/null');
                        } elseif ($binary === 'npm') {
                            $version = shell_exec($path.' --version 2>/dev/null');
                        }

                        if ($version) {
                            $results[$binary][$path]['version'] = mb_trim($version);
                        }
                    } catch (Exception $e) {
                        $results[$binary][$path]['version_error'] = $e->getMessage();
                    }
                }
            }
        }

        Log::info('Queue worker file accessibility', [
            'test_id' => $this->testId,
            'file_accessibility' => $results,
        ]);
    }

    /**
     * Test PDF generation in queue worker
     */
    private function testPdfGeneration(): void
    {
        try {
            $html = '
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Queue Worker Test</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .info { background: #f0f0f0; padding: 10px; margin: 10px 0; }
                    </style>
                </head>
                <body>
                    <h1>Queue Worker Environment Test</h1>
                    <div class="info">
                        <p><strong>Test ID:</strong> '.$this->testId.'</p>
                        <p><strong>Generated at:</strong> '.now()->format('Y-m-d H:i:s').'</p>
                        <p><strong>Process ID:</strong> '.getmypid().'</p>
                        <p><strong>User:</strong> '.get_current_user().'</p>
                        <p><strong>Working Directory:</strong> '.getcwd().'</p>
                        <p><strong>PHP Version:</strong> '.PHP_VERSION.'</p>
                    </div>
                    <div class="info">
                        <h2>Environment Variables</h2>
                        <p><strong>CHROME_PATH:</strong> '.(env('CHROME_PATH') ?: 'NOT_SET').'</p>
                        <p><strong>NODE_BINARY_PATH:</strong> '.(env('NODE_BINARY_PATH') ?: 'NOT_SET').'</p>
                        <p><strong>NPM_BINARY_PATH:</strong> '.(env('NPM_BINARY_PATH') ?: 'NOT_SET').'</p>
                    </div>
                </body>
                </html>
            ';

            $testPath = storage_path(sprintf('app/queue-worker-test-%s.pdf', $this->testId));

            $options = [
                'format' => 'A4',
                'margin_top' => 10,
                'margin_bottom' => 10,
                'margin_left' => 10,
                'margin_right' => 10,
                'print_background' => true,
                'timeout' => 120,
            ];

            Log::info('Queue worker attempting PDF generation', [
                'test_id' => $this->testId,
                'output_path' => $testPath,
                'options' => $options,
                'html_length' => mb_strlen($html),
            ]);

            $success = BrowsershotService::generatePdf($html, $testPath, $options);

            if ($success && file_exists($testPath)) {
                $fileSize = filesize($testPath);

                Log::info('Queue worker PDF generation successful', [
                    'test_id' => $this->testId,
                    'file_path' => $testPath,
                    'file_size' => $fileSize,
                    'success_flag' => $success,
                ]);

                // Clean up test file
                unlink($testPath);

                Log::info('Queue worker test file cleaned up', [
                    'test_id' => $this->testId,
                    'file_path' => $testPath,
                ]);

            } else {
                Log::error('Queue worker PDF generation failed', [
                    'test_id' => $this->testId,
                    'success_flag' => $success,
                    'file_exists' => file_exists($testPath),
                    'expected_path' => $testPath,
                    'options' => $options,
                ]);

                throw new Exception('PDF generation failed in queue worker');
            }

        } catch (Exception $exception) {
            Log::error('Queue worker PDF generation exception', [
                'test_id' => $this->testId,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'file_path' => $testPath ?? 'unknown',
            ]);
            throw $exception;
        }
    }
}
