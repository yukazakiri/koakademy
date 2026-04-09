<?php

declare(strict_types=1);

namespace App\Services;

use Deprecated;
use Exception;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Fpdi;

final class PdfGenerationService
{
    /**
     * Generate PDF from HTML using Chrome/Google Chrome
     */
    public function generatePdfFromHtml(string $html, string $outputPath, array $options = []): void
    {
        // Create temp HTML file
        $tempHtmlFile = tempnam(sys_get_temp_dir(), 'pdf_').'.html';
        file_put_contents($tempHtmlFile, $html);

        try {
            $chromePath = $this->findChromeExecutable();

            // Ensure output directory exists
            $outputDir = dirname($outputPath);
            if (! file_exists($outputDir)) {
                mkdir($outputDir, 0755, true);
                Log::info("Created output directory: {$outputDir}");
            }

            // Try multiple Chrome approaches for robustness
            try {
                $chromePath = $this->findChromeExecutable();
                $this->attemptPdfGeneration($chromePath, $tempHtmlFile, $outputPath, $options);
            } catch (Exception $e) {
                Log::warning('Direct Chrome detection/generation failed: '.$e->getMessage().'. Attempting Browsershot fallback.');
                $this->generateWithBrowsershotFallback($tempHtmlFile, $outputPath);
            }

            Log::info("PDF generated successfully: {$outputPath}");

        } finally {
            // Clean up temp file
            if (file_exists($tempHtmlFile)) {
                unlink($tempHtmlFile);
            }
        }
    }

    /**
     * Generate PDF from a view with data
     */
    public function generatePdfFromView(string $viewName, array $data, string $outputPath, array $options = []): void
    {
        $html = view($viewName, $data)->render();
        $this->generatePdfFromHtml($html, $outputPath, $options);
    }

    /**
     * Ensure directory exists and return full path
     */
    public function ensureDirectory(string $directory): string
    {
        $fullPath = storage_path($directory);

        if (! file_exists($fullPath)) {
            mkdir($fullPath, 0755, true);
        }

        return $fullPath;
    }

    /**
     * Get schedules storage directory with proper permissions
     */
    #[Deprecated(message: "Use Storage::disk(config('filesystems.default')) instead")]
    public function getSchedulesDirectory(): string
    {
        return $this->ensureDirectory('app/schedules');
    }

    /**
     * Merge multiple PDF files into a single PDF
     *
     * @param  array<string>  $pdfPaths  Array of absolute paths to PDF files to merge
     * @param  string  $outputPath  Absolute path for the merged output PDF
     * @param  bool  $landscape  Whether to use landscape orientation (default: true)
     */
    public function mergePdfs(array $pdfPaths, string $outputPath, bool $landscape = true): void
    {
        if ($pdfPaths === []) {
            throw new Exception('No PDF files provided for merging');
        }

        Log::info('Starting PDF merge', [
            'input_files' => count($pdfPaths),
            'output_path' => $outputPath,
            'landscape' => $landscape,
        ]);

        // Ensure output directory exists
        $outputDir = dirname($outputPath);
        if (! file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $pdf = new Fpdi();

        foreach ($pdfPaths as $index => $pdfPath) {
            if (! file_exists($pdfPath)) {
                Log::warning("PDF file not found during merge: {$pdfPath}");

                continue;
            }

            if (filesize($pdfPath) === 0) {
                Log::warning("Empty PDF file skipped during merge: {$pdfPath}");

                continue;
            }

            try {
                $pageCount = $pdf->setSourceFile($pdfPath);

                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $templateId = $pdf->importPage($pageNo);
                    $size = $pdf->getTemplateSize($templateId);

                    // Use the size from the imported page but maintain landscape orientation
                    if ($landscape) {
                        $pdf->AddPage('L', [$size['width'], $size['height']]);
                    } else {
                        $pdf->AddPage('P', [$size['width'], $size['height']]);
                    }

                    $pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height']);
                }
            } catch (Exception $e) {
                Log::error("Failed to import PDF during merge: {$pdfPath}", [
                    'error' => $e->getMessage(),
                    'index' => $index,
                ]);

                // Continue with other PDFs instead of failing completely
                continue;
            }
        }

        // Save the merged PDF
        $pdf->Output($outputPath, 'F');

        if (! file_exists($outputPath) || filesize($outputPath) === 0) {
            throw new Exception('Failed to generate merged PDF');
        }

        Log::info('PDF merge completed successfully', [
            'output_path' => $outputPath,
            'file_size' => filesize($outputPath),
        ]);
    }

    /**
     * Create a temporary directory for PDF generation
     */
    public function createTempDirectory(string $prefix = 'pdf_'): string
    {
        $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.$prefix.uniqid('', true);

        if (! mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
            throw new Exception("Failed to create temp directory: {$tempDir}");
        }

        Log::info("Created temp directory: {$tempDir}");

        return $tempDir;
    }

    /**
     * Clean up a temporary directory and all its contents
     */
    public function cleanupTempDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $files = glob($directory.DIRECTORY_SEPARATOR.'*');
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        rmdir($directory);
        Log::info("Cleaned up temp directory: {$directory}");
    }

    /**
     * Fallback to default Browsershot configuration
     */
    private function generateWithBrowsershotFallback(string $htmlFile, string $outputPath): void
    {
        Log::info('Attempting PDF generation with Browsershot fallback');

        $html = file_get_contents($htmlFile);

        // Use Browsershot with standard options but default executable path
        \Spatie\Browsershot\Browsershot::html($html)
            ->format('A4')
            ->landscape()
            ->margins(10, 10, 10, 10)
            ->noSandbox() // Essential for Docker environments
            ->setOption('args', ['--disable-dev-shm-usage', '--disable-gpu']) // Additional stability options
            ->save($outputPath);

        if (file_exists($outputPath) && filesize($outputPath) > 0) {
            Log::info('Browsershot fallback succeeded');

            return;
        }

        throw new Exception('Browsershot fallback failed to generate file');
    }

    /**
     * Attempt PDF generation with multiple Chrome fallback approaches
     */
    private function attemptPdfGeneration(string $chromePath, string $htmlFile, string $outputPath, array $options): void
    {
        $attempts = [
            'standard' => fn () => $this->generateWithStandardChrome($chromePath, $htmlFile, $outputPath, $options),
            'minimal' => fn () => $this->generateWithMinimalChrome($chromePath, $htmlFile, $outputPath),
            'alternative' => fn () => $this->generateWithAlternativePaths($htmlFile, $outputPath),
        ];

        $lastError = null;

        foreach ($attempts as $attemptName => $attemptFn) {
            try {
                Log::info("Attempting PDF generation with method: {$attemptName}");

                $attemptFn();

                // Verify the PDF was created and is not empty
                if (file_exists($outputPath) && filesize($outputPath) > 0) {
                    Log::info("PDF generation successful with method: {$attemptName}");

                    return;
                }

                Log::warning("PDF generation method {$attemptName} produced empty or no file");

            } catch (Exception $e) {
                $lastError = $e;
                Log::warning("PDF generation method {$attemptName} failed: ".$e->getMessage());

                // Clean up any partial file
                if (file_exists($outputPath)) {
                    unlink($outputPath);
                }
            }
        }

        // All attempts failed
        throw $lastError ?: new Exception('All Chrome PDF generation methods failed');
    }

    /**
     * Standard Chrome generation with full options
     */
    private function generateWithStandardChrome(string $chromePath, string $htmlFile, string $outputPath, array $options): void
    {
        $command = $this->buildChromeCommand($chromePath, $htmlFile, $outputPath, $options);
        $this->executeChromeCommand($command, $outputPath, 'standard');
    }

    /**
     * Minimal Chrome generation with essential options only
     */
    private function generateWithMinimalChrome(string $chromePath, string $htmlFile, string $outputPath): void
    {
        $minimalOptions = [
            'headless' => true,
            'no-sandbox' => true,
            'disable-gpu' => true,
        ];

        $command = $this->buildChromeCommand($chromePath, $htmlFile, $outputPath, $minimalOptions);
        $this->executeChromeCommand($command, $outputPath, 'minimal');
    }

    /**
     * Try alternative Google Chrome paths
     */
    private function generateWithAlternativePaths(string $htmlFile, string $outputPath): void
    {
        // Focus only on Google Chrome paths as requested
        $alternativePaths = [
            '/usr/bin/google-chrome-stable',
            '/usr/bin/google-chrome',
            '/usr/local/bin/google-chrome-stable',
            '/usr/local/bin/google-chrome',
            '/opt/google/chrome/chrome',
            '/snap/bin/google-chrome-stable',
            '/snap/bin/google-chrome',
            '/usr/bin/chromium',
        ];

        foreach ($alternativePaths as $path) {
            if (file_exists($path)) {
                try {
                    Log::info("Trying alternative Google Chrome path: {$path}");

                    $minimalOptions = [
                        'headless' => true,
                        'no-sandbox' => true,
                        'disable-gpu' => true,
                    ];

                    $command = $this->buildChromeCommand($path, $htmlFile, $outputPath, $minimalOptions);
                    $this->executeChromeCommand($command, $outputPath, "alternative-{$path}");

                    if (file_exists($outputPath) && filesize($outputPath) > 0) {
                        Log::info("Alternative Google Chrome path {$path} succeeded");

                        return;
                    }
                } catch (Exception $e) {
                    Log::warning("Alternative Google Chrome path {$path} failed: ".$e->getMessage());

                    continue;
                }
            } else {
                Log::info("Alternative Google Chrome path not found: {$path}");
            }
        }

        throw new Exception('All alternative Google Chrome paths failed');
    }

    /**
     * Execute Chrome command with enhanced error handling for Docker environments
     */
    private function executeChromeCommand(string $command, string $outputPath, string $method): void
    {
        Log::info("Executing Chrome command ({$method}): {$command}");
        Log::info('Current working directory: '.getcwd());
        Log::info('User: '.exec('whoami'));
        Log::info('Environment DISPLAY: '.(getenv('DISPLAY') ?: 'Not set'));

        // Docker-friendly environment variables for Google Chrome
        $envVars = [
            'DISPLAY' => ':99', // Virtual display for Docker
            'HOME' => '/tmp',
            'CHROME_BIN' => '/usr/bin/google-chrome-stable',
            'GOOGLE_CHROME_BIN' => '/usr/bin/google-chrome-stable',
        ];

        // Build command with environment variables
        $envCommand = '';
        foreach ($envVars as $key => $value) {
            $envCommand .= "{$key}=".escapeshellarg($value).' ';
        }

        // Create a temporary file for stderr capture
        $stderrFile = tempnam(sys_get_temp_dir(), 'chrome_stderr_');

        try {
            // Execute with proper error handling
            $output = [];
            $returnCode = 0;

            // Redirect stderr to a file for better error capture
            $commandWithStderr = $command.' 2>'.escapeshellarg($stderrFile);
            exec($commandWithStderr, $output, $returnCode);

            // Read stderr content
            $stderrContent = file_exists($stderrFile) ? file_get_contents($stderrFile) : '';

            Log::info("Chrome execution ({$method}) - Return code: {$returnCode}");
            Log::info("Chrome execution ({$method}) - stdout: ".implode("\n", $output));
            Log::info("Chrome execution ({$method}) - stderr: ".$stderrContent);
            Log::info('Output file exists: '.(file_exists($outputPath) ? 'Yes' : 'No'));

            if (file_exists($outputPath)) {
                Log::info('Output file size: '.filesize($outputPath).' bytes');
            }

            if ($returnCode !== 0 || ! file_exists($outputPath) || filesize($outputPath) === 0) {
                $errorMessage = "Chrome execution failed ({$method}). Return code: {$returnCode}. ";
                $errorMessage .= 'stdout: '.implode("\n", $output).'. ';
                $errorMessage .= 'stderr: '.$stderrContent;

                // Additional Docker-specific debugging
                $errorMessage .= '. Docker environment info: ';
                $errorMessage .= 'User: '.exec('whoami').'. ';
                $errorMessage .= 'PWD: '.getcwd().'. ';
                $errorMessage .= 'Available Chrome binaries: ';

                $chromeBinaries = [];
                exec('ls /usr/bin/ 2>/dev/null | grep -i chrome', $chromeBinaries);
                exec('ls /usr/bin/ 2>/dev/null | grep -i chromium', $chromeBinaries);
                $errorMessage .= implode(', ', $chromeBinaries);

                throw new Exception($errorMessage);
            }

        } finally {
            // Clean up stderr file
            if (file_exists($stderrFile)) {
                unlink($stderrFile);
            }
        }
    }

    /**
     * Build Chrome command with Docker-friendly options
     */
    private function buildChromeCommand(string $chromePath, string $htmlFile, string $outputPath, array $options): string
    {
        // Simplified Docker-optimized default options to avoid timeouts
        $defaultOptions = [
            'headless' => true,
            'no-sandbox' => true,
            'disable-dev-shm-usage' => true,
            'disable-gpu' => true,
            'virtual-time-budget' => 5000, // Reduced timeout
            'disable-extensions' => true,
            'no-first-run' => true,
            'no-default-browser-check' => true,
            'disable-background-timer-throttling' => true,
            'disable-backgrounding-occluded-windows' => true,
            'disable-renderer-backgrounding' => true,
        ];

        $options = array_merge($defaultOptions, $options);

        $commandParts = [escapeshellarg($chromePath)];

        foreach ($options as $key => $value) {
            if (is_bool($value)) {
                if ($value) {
                    $commandParts[] = "--{$key}";
                }
            } else {
                $commandParts[] = "--{$key}=".escapeshellarg((string) $value);
            }
        }

        $commandParts[] = '--print-to-pdf='.escapeshellarg($outputPath);
        $commandParts[] = escapeshellarg($htmlFile);

        return implode(' ', $commandParts);
    }

    /**
     * Find Chrome executable path (prioritize Google Chrome Stable)
     */
    private function findChromeExecutable(): string
    {
        // Prioritize Google Chrome Stable as requested for Docker environment
        $possiblePaths = [
            '/usr/bin/google-chrome-stable',
            '/usr/bin/google-chrome',
            '/usr/local/bin/google-chrome-stable',
            '/usr/local/bin/google-chrome',
            '/opt/google/chrome/chrome',
            '/snap/bin/google-chrome-stable',
            '/snap/bin/google-chrome',
        ];

        Log::info('Searching for Google Chrome executable in Docker environment');
        Log::info('Current working directory: '.getcwd());
        Log::info('User: '.exec('whoami'));

        foreach ($possiblePaths as $path) {
            Log::info("Checking Google Chrome path: {$path}");
            if (file_exists($path)) {
                Log::info("Found Google Chrome at: {$path}");

                // Test if it's executable
                if (is_executable($path)) {
                    Log::info("Google Chrome is executable: {$path}");

                    // Test Chrome version
                    $versionOutput = [];
                    exec($path.' --version 2>&1', $versionOutput, $returnCode);
                    Log::info("Google Chrome version test ({$path}) - Return code: {$returnCode}, Output: ".implode("\n", $versionOutput));

                    return $path;
                }
                Log::warning("Google Chrome found but not executable: {$path}");

            }
        }

        // Try to find Chrome using 'which' command (Google Chrome only)
        $chromeCommands = ['google-chrome-stable', 'google-chrome'];
        foreach ($chromeCommands as $chromeCommand) {
            $whichOutput = [];
            exec("which {$chromeCommand} 2>&1", $whichOutput, $returnCode);
            if ($returnCode === 0 && (isset($whichOutput[0]) && ($whichOutput[0] !== '' && $whichOutput[0] !== '0'))) {
                $foundPath = mb_trim($whichOutput[0]);
                Log::info("Found Google Chrome via 'which': {$foundPath}");
                if (file_exists($foundPath) && is_executable($foundPath)) {
                    return $foundPath;
                }
            }
        }

        // Log all available Google Chrome binaries for debugging
        $debugDirs = ['/usr/bin', '/usr/local/bin', '/opt', '/snap/bin'];
        foreach ($debugDirs as $dir) {
            if (is_dir($dir)) {
                $chromeBinaries = [];
                exec("ls {$dir} 2>/dev/null | grep -i chrome", $chromeBinaries);
                if ($chromeBinaries !== []) {
                    Log::info("Found Google Chrome binaries in {$dir}: ".implode(', ', $chromeBinaries));
                }
            }
        }

        throw new Exception('Google Chrome executable not found. Please install Google Chrome Stable. Checked paths: '.implode(', ', $possiblePaths));
    }
}
