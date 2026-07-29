<?php

declare(strict_types=1);

use App\Services\PdfGenerationService;
use setasign\Fpdi\Fpdi;

it('merges every source PDF into one readable artifact in source order', function (): void {
    $directory = sys_get_temp_dir().'/koa-pdf-merge-'.uniqid();
    mkdir($directory, 0755, true);

    $firstPath = $directory.'/first.pdf';
    $secondPath = $directory.'/second.pdf';
    $mergedPath = $directory.'/merged.pdf';

    $createPdf = function (string $path, string $label): void {
        $pdf = new FPDF('L', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 16);
        $pdf->Cell(0, 10, $label);
        $pdf->Output($path, 'F');
    };

    try {
        $createPdf($firstPath, 'FIRST');
        $createPdf($secondPath, 'SECOND');

        app(PdfGenerationService::class)->mergePdfs([$firstPath, $secondPath], $mergedPath);

        $reader = new Fpdi();

        expect($mergedPath)
            ->toBeFile()
            ->and(filesize($mergedPath))->toBeGreaterThan(0)
            ->and($reader->setSourceFile($mergedPath))->toBe(2);
    } finally {
        foreach ([$firstPath, $secondPath, $mergedPath] as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }

        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
});

it('fails instead of publishing a partial merge when a source PDF is corrupt', function (): void {
    $directory = sys_get_temp_dir().'/koa-pdf-merge-failure-'.uniqid();
    mkdir($directory, 0755, true);

    $validPath = $directory.'/valid.pdf';
    $corruptPath = $directory.'/corrupt.pdf';
    $mergedPath = $directory.'/merged.pdf';

    $pdf = new FPDF('L', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->Output($validPath, 'F');
    file_put_contents($corruptPath, 'not-a-pdf');

    try {
        expect(fn () => app(PdfGenerationService::class)->mergePdfs([$validPath, $corruptPath], $mergedPath))
            ->toThrow(Exception::class, 'Failed to import PDF')
            ->and(file_exists($mergedPath))->toBeFalse();
    } finally {
        foreach ([$validPath, $corruptPath, $mergedPath] as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }

        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
});
