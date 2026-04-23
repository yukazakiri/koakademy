<?php

declare(strict_types=1);

use App\Services\PdfGenerationService;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function (): void {
    Route::get('/test-pdf-generation', function () {
        try {
            $html = '<html><body style="padding: 50px; font-family: Arial;">
            <h1>Test PDF Generation</h1>
            <p>This is a test PDF to verify PDF generation is working correctly.</p>
            <p>Generated at: '.now().'</p>
            <ul>
                <li>Driver: '.config('laravel-pdf.driver').'</li>
                <li>Environment: '.config('app.env').'</li>
            </ul>
        </body></html>';

            $pdfService = app(PdfGenerationService::class);
            $tempPath = tempnam(sys_get_temp_dir(), 'pdf_').'.pdf';

            $pdfService->generatePdfFromHtml($html, $tempPath, [
                'format' => 'A4',
                'margin_top' => 10,
                'margin_right' => 10,
                'margin_bottom' => 10,
                'margin_left' => 10,
                'print_background' => true,
            ]);

            $pdf = file_get_contents($tempPath);
            unlink($tempPath);

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="test.pdf"',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'driver' => config('laravel-pdf.driver'),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    })->name('test.pdf');
});
