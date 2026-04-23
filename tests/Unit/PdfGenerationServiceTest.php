<?php

declare(strict_types=1);

use App\Services\PdfGenerationService;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

beforeEach(function (): void {
    Pdf::fake();
});

it('generates PDFs from HTML through laravel-pdf and maps core options', function (): void {
    $outputPath = sys_get_temp_dir().'/koa-pdf-service-tests/generated-html.pdf';
    $savedPdf = null;
    $savedPath = null;

    app(PdfGenerationService::class)->generatePdfFromHtml('<h1>KoAkademy PDF</h1>', $outputPath, [
        'driver' => 'dompdf',
        'format' => 'A4',
        'landscape' => true,
        'margin_top' => '10mm',
        'margin_right' => '8mm',
        'margin_bottom' => '6mm',
        'margin_left' => '4mm',
        'print_background' => true,
    ]);

    Pdf::assertSaved(function (PdfBuilder $pdf, string $path) use (&$savedPdf, &$savedPath): bool {
        $savedPdf = $pdf;
        $savedPath = $path;

        return true;
    });

    expect($savedPath)->toBe($outputPath)
        ->and($savedPdf)->toBeInstanceOf(PdfBuilder::class)
        ->and($savedPdf->html)->toContain('KoAkademy PDF')
        ->and($savedPdf->format)->toBe('A4')
        ->and($savedPdf->orientation)->toBe('Landscape')
        ->and($savedPdf->margins)->toBe([
            'top' => 10.0,
            'right' => 8.0,
            'bottom' => 6.0,
            'left' => 4.0,
            'unit' => 'mm',
        ]);

    $reflection = new ReflectionClass($savedPdf);
    $driverProperty = $reflection->getProperty('driverName');
    $driverProperty->setAccessible(true);

    expect($driverProperty->getValue($savedPdf))->toBe('dompdf');
});

it('generates PDFs from a Blade view through laravel-pdf', function (): void {
    $outputPath = sys_get_temp_dir().'/koa-pdf-service-tests/generated-view.pdf';

    app(PdfGenerationService::class)->generatePdfFromView('testing.pdf-generation-service', [
        'name' => 'KoAkademy',
    ], $outputPath, [
        'format' => 'Letter',
    ]);

    Pdf::assertSaved(function (PdfBuilder $pdf, string $path): bool {
        return $path === sys_get_temp_dir().'/koa-pdf-service-tests/generated-view.pdf'
            && $pdf->viewName === 'testing.pdf-generation-service'
            && ($pdf->viewData['name'] ?? null) === 'KoAkademy'
            && $pdf->format === 'Letter';
    });
});

it('creates missing output directories before generating PDFs', function (): void {
    $baseDirectory = sys_get_temp_dir().'/koa-pdf-service-tests/'.uniqid('dir_', true);
    $outputPath = $baseDirectory.'/nested/generated.pdf';

    app(PdfGenerationService::class)->generatePdfFromHtml('<p>Directory check</p>', $outputPath);

    expect(is_dir(dirname($outputPath)))->toBeTrue();

    rmdir(dirname($outputPath));
    rmdir($baseDirectory);
});
