<?php

declare(strict_types=1);

use App\Services\PdfGenerationService;
use Illuminate\Support\Facades\File;

uses()->group('pdf', 'driver-matrix');

/**
 * Check whether a driver package is installed.
 */
function isDriverPackageInstalled(string $driver): bool
{
    return match ($driver) {
        'dompdf' => class_exists(\Dompdf\Dompdf::class),
        'gotenberg' => class_exists(\Sensiolabs\GotenbergBundle\GotenbergPdfInterface::class),
        'weasyprint' => class_exists(\Pontedilana\PhpWeasyPrint\Pdf::class),
        'browsershot' => class_exists(\Spatie\Browsershot\Browsershot::class),
        'cloudflare' => true, // cloudflare is HTTP-based, no composer package required
        default => false,
    };
}

/**
 * Check whether external binaries required by a driver are available.
 */
function isDriverBinaryAvailable(string $driver): bool
{
    return match ($driver) {
        'browsershot' => ! empty(shell_exec('which node 2>/dev/null')) && (! empty(shell_exec('which google-chrome-stable 2>/dev/null')) || ! empty(shell_exec('which chromium 2>/dev/null')) || ! empty(shell_exec('which chromium-browser 2>/dev/null'))),
        'weasyprint' => ! empty(shell_exec('which weasyprint 2>/dev/null')),
        default => true,
    };
}

/**
 * Check whether external service credentials are configured.
 */
function isDriverServiceConfigured(string $driver): bool
{
    return match ($driver) {
        'cloudflare' => ! empty(config('laravel-pdf.cloudflare.api_token')) && ! empty(config('laravel-pdf.cloudflare.account_id')),
        default => true,
    };
}

/**
 * Return a minimal HTML fixture for PDF generation tests.
 */
function pdfTestHtml(): string
{
    return '<!DOCTYPE html><html><head><title>Test</title></head><body><h1>Driver Matrix Test</h1><p>Generated at '.now()->toDateTimeString().'</p></body></html>';
}

/**
 * Common assertions for generated PDF content.
 */
function assertValidPdf(string $filePath): void
{
    expect(file_exists($filePath))->toBeTrue("PDF file should exist at {$filePath}");
    expect(filesize($filePath))->toBeGreaterThan(0, 'PDF file should not be empty');

    $handle = fopen($filePath, 'rb');
    $header = fread($handle, 4);
    fclose($handle);

    expect($header)->toBe('%PDF', 'File should start with PDF magic bytes');
}

// =============================================================================
// Driver Availability Sanity Checks
// =============================================================================

test('dompdf package is installed or test is skipped', function (): void {
    if (! isDriverPackageInstalled('dompdf')) {
        $this->markTestSkipped('dompdf/dompdf is not installed. Run: composer require dompdf/dompdf');
    }

    expect(isDriverPackageInstalled('dompdf'))->toBeTrue();
});

test('gotenberg package is installed or test is skipped', function (): void {
    if (! isDriverPackageInstalled('gotenberg')) {
        $this->markTestSkipped('Gotenberg package is not installed.');
    }

    expect(isDriverPackageInstalled('gotenberg'))->toBeTrue();
});

test('weasyprint package is installed or test is skipped', function (): void {
    if (! isDriverPackageInstalled('weasyprint')) {
        $this->markTestSkipped('pontedilana/php-weasyprint is not installed.');
    }

    expect(isDriverPackageInstalled('weasyprint'))->toBeTrue();
});

test('browsershot package and chrome binary are available or test is skipped', function (): void {
    if (! isDriverPackageInstalled('browsershot')) {
        $this->markTestSkipped('spatie/browsershot is not installed.');
    }

    if (! isDriverBinaryAvailable('browsershot')) {
        $this->markTestSkipped('Chrome/Chromium binary not found in PATH.');
    }

    expect(isDriverPackageInstalled('browsershot'))->toBeTrue();
    expect(isDriverBinaryAvailable('browsershot'))->toBeTrue();
});

test('cloudflare credentials are configured or test is skipped', function (): void {
    if (! isDriverServiceConfigured('cloudflare')) {
        $this->markTestSkipped('Cloudflare API token or account ID is not configured.');
    }

    expect(isDriverServiceConfigured('cloudflare'))->toBeTrue();
});

// =============================================================================
// Driver Matrix Generation Tests
// =============================================================================

test('dompdf generates a valid pdf', function (): void {
    if (! isDriverPackageInstalled('dompdf')) {
        $this->markTestSkipped('dompdf/dompdf is not installed.');
    }

    config()->set('laravel-pdf.driver', 'dompdf');

    $service = app(PdfGenerationService::class);
    $tempPath = tempnam(sys_get_temp_dir(), 'pdf_dompdf_').'.pdf';

    try {
        $service->generatePdfFromHtml(pdfTestHtml(), $tempPath);
        assertValidPdf($tempPath);
    } finally {
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }
    }
});

test('browsershot generates a valid pdf', function (): void {
    if (! isDriverPackageInstalled('browsershot')) {
        $this->markTestSkipped('spatie/browsershot is not installed.');
    }

    if (! isDriverBinaryAvailable('browsershot')) {
        $this->markTestSkipped('Chrome/Chromium binary not found in PATH.');
    }

    config()->set('laravel-pdf.driver', 'browsershot');

    $service = app(PdfGenerationService::class);
    $tempPath = tempnam(sys_get_temp_dir(), 'pdf_browsershot_').'.pdf';

    try {
        $service->generatePdfFromHtml(pdfTestHtml(), $tempPath);
        assertValidPdf($tempPath);
    } finally {
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }
    }
});

test('cloudflare generates a valid pdf', function (): void {
    if (! isDriverServiceConfigured('cloudflare')) {
        $this->markTestSkipped('Cloudflare API credentials are not configured.');
    }

    config()->set('laravel-pdf.driver', 'cloudflare');

    $service = app(PdfGenerationService::class);
    $tempPath = tempnam(sys_get_temp_dir(), 'pdf_cloudflare_').'.pdf';

    try {
        $service->generatePdfFromHtml(pdfTestHtml(), $tempPath);
        assertValidPdf($tempPath);
    } finally {
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }
    }
})->skip(fn () => ! isDriverServiceConfigured('cloudflare'), 'Cloudflare credentials not configured');

test('gotenberg generates a valid pdf', function (): void {
    if (! isDriverPackageInstalled('gotenberg')) {
        $this->markTestSkipped('Gotenberg package is not installed.');
    }

    config()->set('laravel-pdf.driver', 'gotenberg');

    $service = app(PdfGenerationService::class);
    $tempPath = tempnam(sys_get_temp_dir(), 'pdf_gotenberg_').'.pdf';

    try {
        $service->generatePdfFromHtml(pdfTestHtml(), $tempPath);
        assertValidPdf($tempPath);
    } finally {
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }
    }
});

test('weasyprint generates a valid pdf', function (): void {
    if (! isDriverPackageInstalled('weasyprint')) {
        $this->markTestSkipped('pontedilana/php-weasyprint is not installed.');
    }

    if (! isDriverBinaryAvailable('weasyprint')) {
        $this->markTestSkipped('weasyprint binary not found in PATH.');
    }

    config()->set('laravel-pdf.driver', 'weasyprint');

    $service = app(PdfGenerationService::class);
    $tempPath = tempnam(sys_get_temp_dir(), 'pdf_weasyprint_').'.pdf';

    try {
        $service->generatePdfFromHtml(pdfTestHtml(), $tempPath);
        assertValidPdf($tempPath);
    } finally {
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }
    }
});

// =============================================================================
// Regression / Framework Mechanics
// =============================================================================

test('pdf generation service applies driver option correctly', function (): void {
    $service = app(PdfGenerationService::class);

    $reflection = new ReflectionMethod($service, 'resolveProfileOptions');
    $reflection->setAccessible(true);

    config()->set('laravel-pdf.driver', 'dompdf');

    $resolved = $reflection->invoke($service, null, ['driver' => 'browsershot']);

    expect($resolved['driver'])->toBe('browsershot');
});

test('pdf generation service ensures output directory exists', function (): void {
    $service = app(PdfGenerationService::class);

    $tempDir = sys_get_temp_dir().'/pdf_test_'.uniqid();
    $outputPath = $tempDir.'/nested/dir/test.pdf';

    $reflection = new ReflectionMethod($service, 'ensureOutputDirectory');
    $reflection->setAccessible(true);
    $reflection->invoke($service, $outputPath);

    expect(is_dir($tempDir.'/nested/dir'))->toBeTrue();

    File::deleteDirectory($tempDir);
});
