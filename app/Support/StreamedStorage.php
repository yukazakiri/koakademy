<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class StreamedStorage
{
    /**
     * @param  array<string, mixed>|string|null  $options
     */
    public static function putFileFromPath(string $disk, string $path, string $sourcePath, array|string|null $options = null): void
    {
        $stream = @fopen($sourcePath, 'rb');

        if ($stream === false) {
            throw new RuntimeException(sprintf('Unable to open stream for [%s].', $sourcePath));
        }

        try {
            $stored = Storage::disk($disk)->put($path, $stream, $options ?? []);
        } finally {
            fclose($stream);
        }

        if ($stored === false) {
            throw new RuntimeException(sprintf('Unable to store file on disk [%s] at path [%s].', $disk, $path));
        }
    }
}
