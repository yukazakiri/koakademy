<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\PrivateExportStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Moves exported files off the web-exposed public disk.
 *
 * Generated exports used to be written to the default disk, which resolves to
 * the public disk. That disk is symlinked into the document root, so the files
 * were downloadable at /storage/... without authentication. The download
 * routes relocate a file the first time an authorized caller reads it, and
 * this command performs the same move for everything that is never read.
 */
final class MigratePrivateExports extends Command
{
    protected $signature = 'exports:migrate-to-private
                            {--dry-run : List the files that would be moved without moving them}';

    protected $description = 'Move generated exports from the web-exposed public disk onto the private export disk';

    public function handle(): int
    {
        $privateDisk = PrivateExportStorage::disk();

        if ($privateDisk === 'public') {
            $this->error('The private export disk is "public", so exports would stay web-accessible. Set FILESYSTEM_PRIVATE_DISK to a non-public disk.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $moved = 0;
        $failed = 0;

        foreach (PrivateExportStorage::EXPORT_DIRECTORIES as $directory) {
            foreach ($this->publicFiles($directory) as $path) {
                if ($dryRun) {
                    $this->line("Would move: {$path}");
                    $moved++;

                    continue;
                }

                if (PrivateExportStorage::relocateFromPublicDisk($path)) {
                    $moved++;
                } else {
                    $failed++;
                    $this->warn("Could not move: {$path}");
                }
            }
        }

        if ($dryRun) {
            $this->info("{$moved} file(s) would be moved to the {$privateDisk} disk.");

            return self::SUCCESS;
        }

        $this->info("Moved {$moved} export file(s) to the {$privateDisk} disk.");

        if ($failed > 0) {
            $this->warn("{$failed} file(s) could not be moved.");
        }

        return self::SUCCESS;
    }

    /**
     * List every file stored under the given directory on the public disk.
     *
     * @return list<string>
     */
    private function publicFiles(string $directory): array
    {
        try {
            $files = Storage::disk('public')->allFiles($directory);
        } catch (Throwable) {
            return [];
        }

        return array_values(array_filter(
            $files,
            static fn (string $path): bool => ! str_contains($path, '..')
        ));
    }
}
