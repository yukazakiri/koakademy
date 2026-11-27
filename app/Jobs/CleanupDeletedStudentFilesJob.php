<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class CleanupDeletedStudentFilesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300];

    /**
     * @param  list<array{disk: string|null, path: string}>  $files
     */
    public function __construct(
        public array $files,
    ) {}

    public function handle(): void
    {
        if ($this->files === []) {
            return;
        }

        $failures = [];
        $seen = [];
        $candidateDisks = array_values(array_unique(array_filter([
            (string) config('filesystems.private_disk', 'private'),
            'private',
            is_string(config('filesystems.default')) ? (string) config('filesystems.default') : null,
            'public',
        ])));

        foreach ($this->files as $entry) {
            $path = mb_trim((string) ($entry['path'] ?? ''));
            $recordedDisk = isset($entry['disk']) && is_string($entry['disk']) && mb_trim($entry['disk']) !== ''
                ? mb_trim($entry['disk'])
                : null;

            if (
                $path === ''
                || str_contains($path, '..')
                || filter_var($path, FILTER_VALIDATE_URL)
                || str_starts_with($path, '/')
            ) {
                continue;
            }

            $disksToCheck = $recordedDisk !== null ? [$recordedDisk] : $candidateDisks;

            foreach ($disksToCheck as $disk) {
                $key = $disk.'|'.$path;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                try {
                    if (Storage::disk($disk)->exists($path)) {
                        $deleted = Storage::disk($disk)->delete($path);
                        if (! $deleted) {
                            $failures[] = "File cleanup failed for {$path} on disk {$disk}";
                            Log::warning('Failed to delete student file after bulk permanent deletion.', [
                                'disk' => $disk,
                                'path' => $path,
                            ]);
                        }
                    }
                } catch (Throwable $e) {
                    $failures[] = "File cleanup failed for {$path} on disk {$disk} ({$e->getMessage()})";
                    Log::warning('Exception while deleting student file after bulk permanent deletion.', [
                        'disk' => $disk,
                        'path' => $path,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($failures !== []) {
            throw new RuntimeException('Failed to delete student files: '.implode('; ', $failures));
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Exhausted retries cleaning up files for permanently deleted students.', [
            'files' => $this->files,
            'error' => $exception->getMessage(),
        ]);
    }
}
