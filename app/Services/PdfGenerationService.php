<?php

declare(strict_types=1);

namespace App\Services;

use Deprecated;
use Exception;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Fpdi;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

final class PdfGenerationService
{
    /**
     * Generate PDF from HTML using spatie/laravel-pdf.
     *
     * @param  array<string, mixed>  $options
     */
    public function generatePdfFromHtml(string $html, string $outputPath, array $options = []): void
    {
        $this->ensureOutputDirectory($outputPath);

        $pdfBuilder = Pdf::html($html);
        $this->applyPdfOptions($pdfBuilder, $options);
        $pdfBuilder->save($outputPath);

        Log::info('PDF generated from HTML using Laravel PDF', [
            'output_path' => $outputPath,
            'driver' => $options['driver'] ?? config('laravel-pdf.driver'),
        ]);
    }

    /**
     * Generate PDF from a view with data
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $options
     */
    public function generatePdfFromView(string $viewName, array $data, string $outputPath, array $options = []): void
    {
        $this->ensureOutputDirectory($outputPath);

        $pdfBuilder = Pdf::view($viewName, $data);
        $this->applyPdfOptions($pdfBuilder, $options);
        $pdfBuilder->save($outputPath);

        Log::info('PDF generated from view using Laravel PDF', [
            'view' => $viewName,
            'output_path' => $outputPath,
            'driver' => $options['driver'] ?? config('laravel-pdf.driver'),
        ]);
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
     * Merge PDFs in bounded chunks to reduce memory and timeout risk.
     *
     * @param  array<string>  $pdfPaths
     */
    public function mergePdfsChunked(array $pdfPaths, string $outputPath, int $chunkSize = 25, bool $landscape = true): void
    {
        if ($pdfPaths === []) {
            throw new Exception('No PDF files provided for chunked merge');
        }

        if ($chunkSize < 2 || count($pdfPaths) <= $chunkSize) {
            $this->mergePdfs($pdfPaths, $outputPath, $landscape);

            return;
        }

        $tempDirectory = $this->createTempDirectory('pdf_merge_chunks_');

        try {
            $partialPaths = [];

            foreach (array_chunk($pdfPaths, $chunkSize) as $chunkIndex => $chunkPaths) {
                $partialPath = sprintf('%s%schunk_%03d.pdf', $tempDirectory, DIRECTORY_SEPARATOR, $chunkIndex);
                $this->mergePdfs($chunkPaths, $partialPath, $landscape);
                $partialPaths[] = $partialPath;
            }

            $this->mergePdfs($partialPaths, $outputPath, $landscape);
        } finally {
            try {
                $this->cleanupTempDirectory($tempDirectory);
            } catch (Exception $exception) {
                Log::warning('Failed to clean up temporary chunk merge directory', [
                    'directory' => $tempDirectory,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
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
     * @param  array<string, mixed>  $options
     */
    private function applyPdfOptions(PdfBuilder $pdfBuilder, array $options): void
    {
        $normalizedOptions = $this->normalizeOptions($options);

        if (isset($normalizedOptions['driver']) && is_string($normalizedOptions['driver'])) {
            $pdfBuilder->driver($normalizedOptions['driver']);
        }

        if (isset($normalizedOptions['format']) && is_string($normalizedOptions['format'])) {
            $pdfBuilder->format($normalizedOptions['format']);
        }

        if (($normalizedOptions['landscape'] ?? false) === true || ($normalizedOptions['orientation'] ?? null) === 'landscape') {
            $pdfBuilder->landscape();
        }

        $margins = $this->resolveMargins($normalizedOptions);
        if ($margins !== null) {
            $pdfBuilder->margins(
                $margins['top'],
                $margins['right'],
                $margins['bottom'],
                $margins['left'],
                $margins['unit']
            );
        }

        if (
            isset($normalizedOptions['paper-width'], $normalizedOptions['paper-height'])
            && is_numeric($normalizedOptions['paper-width'])
            && is_numeric($normalizedOptions['paper-height'])
        ) {
            $pdfBuilder->paperSize(
                (float) $normalizedOptions['paper-width'],
                (float) $normalizedOptions['paper-height'],
                isset($normalizedOptions['paper-unit']) && is_string($normalizedOptions['paper-unit'])
                    ? $normalizedOptions['paper-unit']
                    : 'mm'
            );
        }

        if (isset($normalizedOptions['scale']) && is_numeric($normalizedOptions['scale'])) {
            $pdfBuilder->scale((float) $normalizedOptions['scale']);
        }

        if (isset($normalizedOptions['page-ranges']) && is_string($normalizedOptions['page-ranges'])) {
            $pdfBuilder->pageRanges($normalizedOptions['page-ranges']);
        }

        $this->applyBrowsershotOptions($pdfBuilder, $normalizedOptions);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function applyBrowsershotOptions(PdfBuilder $pdfBuilder, array $options): void
    {
        $arguments = $this->buildBrowsershotArguments($options);

        if ($arguments === [] && ! array_key_exists('print-background', $options)) {
            return;
        }

        $printBackground = $options['print-background'] ?? null;

        $pdfBuilder->withBrowsershot(function (Browsershot $browsershot) use ($arguments, $printBackground): void {
            if ($arguments !== []) {
                $browsershot->setOption('args', $arguments);
            }

            if ($printBackground === true) {
                $browsershot->showBackground();
            }

            if ($printBackground === false) {
                $browsershot->hideBackground();
            }
        });
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function normalizeOptions(array $options): array
    {
        $normalized = [];

        foreach ($options as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $normalizedKey = mb_strtolower(str_replace('_', '-', $key));
            $normalized[$normalizedKey] = $value;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{top: float, right: float, bottom: float, left: float, unit: string}|null
     */
    private function resolveMargins(array $options): ?array
    {
        $marginDefinitions = [];
        $sharedUnit = 'mm';

        if (isset($options['margins']) && is_array($options['margins'])) {
            $marginDefinitions['top'] = $options['margins']['top'] ?? null;
            $marginDefinitions['right'] = $options['margins']['right'] ?? null;
            $marginDefinitions['bottom'] = $options['margins']['bottom'] ?? null;
            $marginDefinitions['left'] = $options['margins']['left'] ?? null;

            if (isset($options['margins']['unit']) && is_string($options['margins']['unit'])) {
                $sharedUnit = mb_strtolower($options['margins']['unit']);
            }
        } else {
            $marginDefinitions['top'] = $options['margin-top'] ?? null;
            $marginDefinitions['right'] = $options['margin-right'] ?? null;
            $marginDefinitions['bottom'] = $options['margin-bottom'] ?? null;
            $marginDefinitions['left'] = $options['margin-left'] ?? null;
        }

        if ($marginDefinitions['top'] === null && $marginDefinitions['right'] === null && $marginDefinitions['bottom'] === null && $marginDefinitions['left'] === null) {
            return null;
        }

        $parsedMargins = [];

        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            [$value, $unit] = $this->parseMarginValue($marginDefinitions[$side], $sharedUnit);
            $parsedMargins[$side] = $value;
            $sharedUnit = $unit;
        }

        $parsedMargins['unit'] = $sharedUnit;

        return $parsedMargins;
    }

    /**
     * @return array{0: float, 1: string}
     */
    private function parseMarginValue(mixed $value, string $fallbackUnit): array
    {
        if ($value === null) {
            return [0.0, $fallbackUnit];
        }

        if (is_int($value) || is_float($value)) {
            return [(float) $value, $fallbackUnit];
        }

        if (! is_string($value)) {
            return [0.0, $fallbackUnit];
        }

        if (preg_match('/^(-?\d+(?:\.\d+)?)\s*(mm|cm|in|px|pt)?$/i', mb_trim($value), $matches) === 1) {
            $numericValue = (float) $matches[1];
            $unit = isset($matches[2]) ? mb_strtolower($matches[2]) : $fallbackUnit;

            return [$numericValue, $unit];
        }

        return [0.0, $fallbackUnit];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, string>
     */
    private function buildBrowsershotArguments(array $options): array
    {
        $reservedOptionKeys = [
            'driver',
            'format',
            'landscape',
            'orientation',
            'margins',
            'margin-top',
            'margin-right',
            'margin-bottom',
            'margin-left',
            'paper-width',
            'paper-height',
            'paper-unit',
            'scale',
            'page-ranges',
            'print-background',
        ];

        $arguments = [];

        foreach ($options as $key => $value) {
            if (in_array($key, $reservedOptionKeys, true)) {
                continue;
            }

            if ($key === 'args' && is_array($value)) {
                foreach ($value as $argument) {
                    if (is_string($argument)) {
                        $arguments[] = $argument;
                    }
                }

                continue;
            }

            if ($value === true) {
                $arguments[] = "--{$key}";

                continue;
            }

            if ($value === false || $value === null || is_array($value) || is_object($value)) {
                continue;
            }

            $arguments[] = "--{$key}={$value}";
        }

        return $arguments;
    }

    private function ensureOutputDirectory(string $outputPath): void
    {
        $outputDirectory = dirname($outputPath);

        if (is_dir($outputDirectory)) {
            return;
        }

        if (! mkdir($outputDirectory, 0755, true) && ! is_dir($outputDirectory)) {
            throw new Exception("Unable to create PDF output directory: {$outputDirectory}");
        }
    }
}
