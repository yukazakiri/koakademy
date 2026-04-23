<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\PdfGenerationService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class TestPdfGenerationJob implements ShouldQueue
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
        Log::info('Starting PDF generation test job', [
            'test_id' => $this->testId,
            'process_id' => getmypid(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
        ]);

        try {
            // Test 1: Environment Variables
            $this->testEnvironmentVariables();

            // Test 2: PDF Generation
            $this->testPdfGeneration();

            Log::info('PDF generation test job completed successfully', [
                'test_id' => $this->testId,
            ]);
        } catch (Exception $exception) {
            Log::error('PDF generation test job failed', [
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
        Log::error('PDF generation test job failed permanently', [
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
            'LARAVEL_PDF_DRIVER',
            'CLOUDFLARE_API_TOKEN',
            'CLOUDFLARE_ACCOUNT_ID',
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
                        <p><strong>LARAVEL_PDF_DRIVER:</strong> '.(env('LARAVEL_PDF_DRIVER') ?: 'NOT_SET').'</p>
                        <p><strong>CLOUDFLARE_API_TOKEN:</strong> '.(env('CLOUDFLARE_API_TOKEN') ? 'SET' : 'NOT_SET').'</p>
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
            ];

            Log::info('Queue worker attempting PDF generation', [
                'test_id' => $this->testId,
                'output_path' => $testPath,
                'options' => $options,
                'html_length' => mb_strlen($html),
            ]);

            $pdfService = app(PdfGenerationService::class);
            $pdfService->generatePdfFromHtml($html, $testPath, $options);

            if (file_exists($testPath) && filesize($testPath) > 0) {
                $fileSize = filesize($testPath);

                Log::info('Queue worker PDF generation successful', [
                    'test_id' => $this->testId,
                    'file_path' => $testPath,
                    'file_size' => $fileSize,
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
