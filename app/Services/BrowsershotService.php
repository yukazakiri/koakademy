<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Legacy BrowsershotService - kept for backward compatibility.
 *
 * @deprecated Use queue-based jobs (GenerateStudentTimetablePdfJob, GenerateTimetablePdfJob) instead.
 */
final class BrowsershotService
{
    /**
     * Generate a PDF from HTML using Browsershot.
     *
     * @param  string  $html  The HTML content to convert
     * @param  string  $outputPath  The path where the PDF should be saved
     * @param  array  $options  PDF generation options
     * @return bool|string True on success, error string on failure
     */
    public static function generatePdf(string $html, string $outputPath, array $options = []): bool|string
    {
        try {
            // Use PdfGenerationService instead of Spatie\Browsershot
            $pdfService = app(PdfGenerationService::class);

            // Map Browsershot options to PdfGenerationService options if needed
            // PdfGenerationService expects keys that will be converted to flags (e.g. 'landscape' -> '--landscape')
            // Existing usages pass keys like 'landscape', 'print_background', 'margin_top'
            // We pass them through as PdfGenerationService seems to handle them (or at least callers expect it to)

            $pdfService->generatePdfFromHtml($html, $outputPath, $options);

            return true;

        } catch (Exception $exception) {
            Log::error('BrowsershotService PDF generation failed (via PdfGenerationService)', [
                'error' => $exception->getMessage(),
                'output_path' => $outputPath,
                'exception_trace' => $exception->getTraceAsString(),
            ]);

            return $exception->getMessage();
        }
    }

    // findChromeExecutable removed as it is no longer used
}
