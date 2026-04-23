<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

final class FixedPdfService
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
            Log::info('FixedPdfService PDF generation starting', [
                'output_path' => $outputPath,
                'options' => $options,
            ]);

            $pdfService = app(PdfGenerationService::class);

            // If output path is provided, save directly
            if (! in_array($outputPath, [null, '', '0'], true)) {
                $pdfService->generatePdfFromHtml($html, $outputPath, $options);
                $success = file_exists($outputPath);

                Log::info('FixedPdfService PDF saved', [
                    'output_path' => $outputPath,
                    'success' => $success,
                ]);

                return $success;
            }

            // Otherwise, save to a temp file and return contents
            $tempPath = tempnam(sys_get_temp_dir(), 'pdf_').'.pdf';
            $pdfService->generatePdfFromHtml($html, $tempPath, $options);
            $pdf = file_get_contents($tempPath);
            unlink($tempPath);

            Log::info('FixedPdfService PDF generated successfully', [
                'pdf_size' => mb_strlen((string) $pdf),
            ]);

            return $pdf;
        } catch (Exception $exception) {
            Log::error('FixedPdfService PDF generation failed', [
                'error' => $exception->getMessage(),
                'options' => $options,
                'output_path' => $outputPath,
            ]);
            throw $exception;
        }
    }
}
