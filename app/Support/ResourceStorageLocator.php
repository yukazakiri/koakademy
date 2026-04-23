<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;

final class ResourceStorageLocator
{
    public static function exists(string $disk, string $storedPath): bool
    {
        if ($storedPath === '') {
            return false;
        }

        if (Storage::disk($disk)->exists($storedPath)) {
            return true;
        }

        $normalizedKey = self::normalizeStorageKey($storedPath);

        if ($normalizedKey !== null && Storage::disk($disk)->exists($normalizedKey)) {
            return true;
        }

        return file_exists($storedPath);
    }

    public static function normalizeStorageKey(string $path): ?string
    {
        $normalizedPath = str_replace('\\', '/', $path);

        $markers = [
            '/storage/app/private/',
            '/storage/app/public/',
            '/storage/app/',
        ];

        foreach ($markers as $marker) {
            $position = mb_strpos($normalizedPath, $marker);

            if ($position === false) {
                continue;
            }

            $relativePath = mb_substr($normalizedPath, $position + mb_strlen($marker));

            return $relativePath !== '' ? $relativePath : null;
        }

        return null;
    }
}
