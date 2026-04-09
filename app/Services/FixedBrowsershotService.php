<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Spatie\Browsershot\Browsershot;

final class FixedBrowsershotService
{
    /**
     * Generate a PDF with fixed configuration.
     */
    public static function generatePdf(
        string $html,
        ?string $outputPath = null,
        array $options = []
    ): bool|string {
        try {
            Log::info('FixedBrowsershotService PDF generation starting', [
                'output_path' => $outputPath,
                'options' => $options,
            ]);

            // Create Browsershot instance with working configuration
            $browsershot = Browsershot::html($html)
                ->setChromePath('/usr/bin/google-chrome-stable')
                ->setNodeBinary('/usr/bin/node')
                ->setNpmBinary('/usr/bin/npm')
                ->noSandbox()
                ->disableSetuidSandbox()
                ->disableDevShmUsage()
                ->disableGpu();

            // Apply PDF options
            if (isset($options['format'])) {
                $browsershot->format($options['format']);
            }

            if (isset($options['landscape']) && $options['landscape']) {
                $browsershot->landscape();
            }

            if (isset($options['margin_top'], $options['margin_bottom'], $options['margin_left'], $options['margin_right'])) {
                $browsershot->margins(
                    (float) $options['margin_top'],
                    (float) $options['margin_right'],
                    (float) $options['margin_bottom'],
                    (float) $options['margin_left']
                );
            }

            if ($options['print_background'] ?? true) {
                $browsershot->printBackground();
            }

            if ($options['timeout'] ?? false) {
                $browsershot->timeout((int) $options['timeout']);
            }

            // Wait for network idle and use print media
            $browsershot->waitUntilNetworkIdle();
            $browsershot->emulateMedia('print');

            Log::info('FixedBrowsershotService generating PDF', [
                'chrome_path' => '/usr/bin/google-chrome-stable',
                'node_path' => '/usr/bin/node',
                'npm_path' => '/usr/bin/npm',
            ]);

            // Generate PDF
            if (! in_array($outputPath, [null, '', '0'], true)) {
                $browsershot->save($outputPath);
                $success = file_exists($outputPath);

                Log::info('FixedBrowsershotService PDF saved', [
                    'output_path' => $outputPath,
                    'success' => $success,
                    'file_exists' => file_exists($outputPath),
                ]);

                return $success;
            }

            $pdf = $browsershot->pdf();

            Log::info('FixedBrowsershotService PDF generated successfully', [
                'pdf_size' => mb_strlen((string) $pdf),
            ]);

            return $pdf;

        } catch (Exception $exception) {
            Log::error('FixedBrowsershotService PDF generation failed', [
                'error' => $exception->getMessage(),
                'options' => $options,
                'output_path' => $outputPath,
                'exception_trace' => $exception->getTraceAsString(),
            ]);
            throw $exception;
        }
    }
}
