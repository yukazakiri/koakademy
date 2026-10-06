<?php

declare(strict_types=1);

use App\Support\StreamedStorage;
use Illuminate\Support\Facades\Storage;

it('stores files from local paths using streams', function (): void {
    Storage::fake('streamed');

    $tempFilePath = tempnam(sys_get_temp_dir(), 'stream_test_');

    if ($tempFilePath === false) {
        throw new RuntimeException('Failed to create temporary test file.');
    }

    $content = str_repeat('PDF-CONTENT-', 64);
    file_put_contents($tempFilePath, $content);

    try {
        StreamedStorage::putFileFromPath('streamed', 'exports/report.pdf', $tempFilePath, ['visibility' => 'public']);

        Storage::disk('streamed')->assertExists('exports/report.pdf');
        expect(Storage::disk('streamed')->get('exports/report.pdf'))->toBe($content);
    } finally {
        if (file_exists($tempFilePath)) {
            unlink($tempFilePath);
        }
    }
});

it('throws a runtime exception when source stream cannot be opened', function (): void {
    Storage::fake('streamed');

    $missingPath = sys_get_temp_dir().'/missing-stream-source-'.uniqid().'.pdf';

    expect(function () use ($missingPath): void {
        StreamedStorage::putFileFromPath('streamed', 'exports/missing.pdf', $missingPath);
    })->toThrow(RuntimeException::class);
});
