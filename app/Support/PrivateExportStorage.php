<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Resolves where generated exports live and keeps them off web-exposed disks.
 *
 * Exports used to be written to the default disk, which resolves to the public
 * disk in most deployments. The public disk is symlinked into the document
 * root, so every exported PDF was reachable at /storage/... without any
 * authentication. Exports now live on the private disk instead.
 *
 * Copies that were written before the migration are still served through the
 * authorized download routes, and are relocated to the private disk the first
 * time an authorized download reads them.
 */
final class PrivateExportStorage
{
    /**
     * Directories whose contents must never stay on a web-exposed disk.
     *
     * @var list<string>
     */
    public const array EXPORT_DIRECTORIES = [
        'assessments',
        'exports/student-lists',
        'schedules',
    ];

    /**
     * The disk generated exports are written to.
     */
    public static function disk(): string
    {
        $disk = mb_trim((string) config('filesystems.private_disk', 'private'));

        return $disk !== '' ? $disk : 'private';
    }

    /**
     * The disks a stored file may live on, most specific first.
     *
     * @param  string|null  $recordDisk  The disk recorded alongside the file, if any.
     * @return list<string>
     */
    public static function candidateDisks(?string $recordDisk = null): array
    {
        $candidates = [];

        if ($recordDisk !== null && mb_trim($recordDisk) !== '') {
            $candidates[] = mb_trim($recordDisk);
        }

        $candidates[] = self::disk();
        $candidates[] = (string) config('filesystems.default');
        $candidates[] = 'public';

        $candidates = array_map(static fn (string $disk): string => mb_trim($disk), $candidates);

        return array_values(array_unique(array_filter($candidates)));
    }

    /**
     * Find the first candidate disk that actually holds the given path.
     *
     * @param  list<string>  $candidateDisks
     */
    public static function locate(string $path, array $candidateDisks): ?string
    {
        if (mb_trim($path) === '') {
            return null;
        }

        foreach ($candidateDisks as $disk) {
            if (mb_trim($disk) === '') {
                continue;
            }

            try {
                if (Storage::disk($disk)->exists($path)) {
                    return $disk;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * Move a legacy copy off the web-exposed public disk onto the private one.
     *
     * The public copy is only removed once the private copy is in place, so a
     * failed relocation never destroys the file.
     */
    public static function relocateFromPublicDisk(string $path): bool
    {
        $privateDisk = self::disk();

        if ($privateDisk === 'public' || mb_trim($path) === '') {
            return false;
        }

        try {
            if (! Storage::disk('public')->exists($path)) {
                return false;
            }

            $contents = Storage::disk('public')->get($path);

            if ($contents === '') {
                return false;
            }

            Storage::disk($privateDisk)->put($path, $contents, ['visibility' => 'private']);
            Storage::disk('public')->delete($path);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
