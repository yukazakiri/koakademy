<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\StreamedStorage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use Throwable;

final class AssessmentExportArtifactService
{
    /** @return array{page_count: int, byte_size: int, checksum: string} */
    public function validateLocalPdf(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException(sprintf('PDF artifact is missing or unreadable: %s', basename($path)));
        }

        $byteSize = filesize($path);
        if ($byteSize === false || $byteSize < 5) {
            throw new RuntimeException(sprintf('PDF artifact is empty: %s', basename($path)));
        }

        $handle = fopen($path, 'rb');
        $signature = $handle === false ? false : fread($handle, 5);
        if (is_resource($handle)) {
            fclose($handle);
        }
        if ($signature !== '%PDF-') {
            throw new RuntimeException(sprintf('Artifact does not contain a PDF signature: %s', basename($path)));
        }

        try {
            $pageCount = (new Fpdi)->setSourceFile($path);
        } catch (Throwable $throwable) {
            throw new RuntimeException(sprintf('PDF artifact cannot be parsed: %s', basename($path)), previous: $throwable);
        }

        if ($pageCount < 1) {
            throw new RuntimeException(sprintf('PDF artifact contains no pages: %s', basename($path)));
        }

        return [
            'page_count' => $pageCount,
            'byte_size' => $byteSize,
            'checksum' => hash_file('sha256', $path),
        ];
    }

    /** @return array{page_count: int, byte_size: int, checksum: string} */
    public function storeValidatedPdf(string $disk, string $storagePath, string $localPath): array
    {
        $metadata = $this->validateLocalPdf($localPath);
        StreamedStorage::putFileFromPath($disk, $storagePath, $localPath, ['visibility' => 'private']);

        $verificationPath = $this->downloadToTemporaryPath($disk, $storagePath);
        try {
            $storedMetadata = $this->validateLocalPdf($verificationPath);
        } finally {
            @unlink($verificationPath);
        }

        if ($metadata['checksum'] !== $storedMetadata['checksum']) {
            Storage::disk($disk)->delete($storagePath);
            throw new RuntimeException(sprintf('Stored PDF checksum mismatch for [%s].', $storagePath));
        }

        return $metadata;
    }

    public function downloadToTemporaryPath(string $disk, string $storagePath): string
    {
        if (! Storage::disk($disk)->exists($storagePath)) {
            throw new RuntimeException(sprintf('Stored PDF is missing: %s', $storagePath));
        }

        $temporary = tempnam(sys_get_temp_dir(), 'assessment_export_');
        if ($temporary === false) {
            throw new RuntimeException('Unable to allocate a temporary PDF path.');
        }
        $localPath = $temporary.'.pdf';
        rename($temporary, $localPath);

        $source = Storage::disk($disk)->readStream($storagePath);
        $target = fopen($localPath, 'wb');
        if ($source === false || $target === false) {
            if (is_resource($source)) {
                fclose($source);
            }
            if (is_resource($target)) {
                fclose($target);
            }
            @unlink($localPath);
            throw new RuntimeException(sprintf('Unable to stream stored PDF: %s', $storagePath));
        }

        try {
            stream_copy_to_stream($source, $target);
        } finally {
            fclose($source);
            fclose($target);
        }

        return $localPath;
    }
}
