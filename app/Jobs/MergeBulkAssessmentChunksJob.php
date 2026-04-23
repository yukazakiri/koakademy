<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\JobTrackerService;
use App\Services\PdfGenerationService;
use App\Support\StreamedStorage;
use Exception;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class MergeBulkAssessmentChunksJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;

    public int $tries = 2;

    public function __construct(
        private string $jobId,
        private int $userId,
        private string $batchId,
        private string $manifestPath,
    ) {
        $this->onQueue('pdf-generation');
    }

    public function handle(JobTrackerService $jobTracker, PdfGenerationService $pdfService): void
    {
        $storageDisk = (string) config('filesystems.default');
        $localChunkPaths = [];
        $mergedOutputPath = null;

        try {
            $chunkStates = $this->loadChunkStates($storageDisk);

            if ($chunkStates->isEmpty()) {
                throw new Exception('No chunk state files were found for bulk assessment merge.');
            }

            /** @var Collection<int, array<string, mixed>> $failedChunks */
            $failedChunks = $chunkStates
                ->where('status', 'failed')
                ->values();

            /** @var Collection<int, array<string, mixed>> $completedChunks */
            $completedChunks = $chunkStates
                ->where('status', 'completed')
                ->sortBy(fn (array $state): int => (int) ($state['chunk_index'] ?? 0))
                ->values();

            if ($failedChunks->isNotEmpty()) {
                $failedChunksPath = sprintf('bulk_assessments/%s/failed_chunks.json', $this->jobId);

                Storage::disk($storageDisk)->put(
                    $failedChunksPath,
                    json_encode($failedChunks->all(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
                    ['visibility' => 'private']
                );

                $jobTracker->markFailed(
                    $this->jobId,
                    sprintf('Bulk assessment generation failed for %d chunk(s). Retry failed jobs in batch %s.', $failedChunks->count(), $this->batchId)
                );

                $this->notifyFailure($failedChunks->count());

                return;
            }

            if ($completedChunks->isEmpty()) {
                throw new Exception('No completed chunk outputs were found for merging.');
            }

            $jobTracker->updateProgress(
                $this->jobId,
                85,
                sprintf('Merging %d chunk PDFs...', $completedChunks->count()),
                metadata: [
                    'batch_id' => $this->batchId,
                    'manifest_path' => $this->manifestPath,
                ]
            );

            foreach ($completedChunks as $chunkState) {
                $storagePath = (string) ($chunkState['storage_path'] ?? '');

                if ($storagePath === '') {
                    continue;
                }

                $localChunkPaths[] = $this->downloadChunkToLocalPath($storageDisk, $storagePath);
            }

            if ($localChunkPaths === []) {
                throw new Exception('Unable to load any chunk PDF files for merging.');
            }

            $mergedOutputBasePath = tempnam(sys_get_temp_dir(), 'bulk_assessments_final_');

            if ($mergedOutputBasePath === false) {
                throw new Exception('Failed to allocate temporary path for final merged PDF.');
            }

            $mergedOutputPath = $mergedOutputBasePath.'.pdf';
            rename($mergedOutputBasePath, $mergedOutputPath);

            $pdfService->mergePdfsChunked($localChunkPaths, $mergedOutputPath, 20, true);

            $finalStoragePath = sprintf(
                'bulk_assessments/%s/bulk_assessments_%s.pdf',
                $this->jobId,
                now()->format('Y-m-d_H-i-s')
            );

            StreamedStorage::putFileFromPath($storageDisk, $finalStoragePath, $mergedOutputPath, ['visibility' => 'public']);

            $downloadUrl = Storage::disk($storageDisk)->url($finalStoragePath);
            $skippedStudents = $this->collectSkippedStudents($chunkStates);
            $reportUrl = $this->storeSkippedStudentsReport($storageDisk, $skippedStudents);

            $successCount = (int) $completedChunks->sum(fn (array $state): int => (int) ($state['generated_count'] ?? 0));
            $message = sprintf('Generated %d assessments successfully.', $successCount);

            if ($skippedStudents !== []) {
                $message .= sprintf(' (%d skipped)', count($skippedStudents));
            }

            $jobTracker->markCompleted(
                $this->jobId,
                $message,
                $downloadUrl,
                [
                    'batch_id' => $this->batchId,
                    'manifest_path' => $this->manifestPath,
                    'chunk_count' => $completedChunks->count(),
                    'skipped_count' => count($skippedStudents),
                    'report_url' => $reportUrl,
                ]
            );

            $this->notifySuccess($downloadUrl, $successCount, $reportUrl);
        } catch (Throwable $throwable) {
            Log::error('Failed to merge bulk assessment chunk outputs', [
                'job_id' => $this->jobId,
                'batch_id' => $this->batchId,
                'error' => $throwable->getMessage(),
            ]);

            $jobTracker->markFailed($this->jobId, $throwable->getMessage());
            $this->notifyFailure(0);

            throw $throwable;
        } finally {
            foreach ($localChunkPaths as $localChunkPath) {
                if (file_exists($localChunkPath)) {
                    unlink($localChunkPath);
                }
            }

            if ($mergedOutputPath !== null && file_exists($mergedOutputPath)) {
                unlink($mergedOutputPath);
            }
        }
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function loadChunkStates(string $storageDisk): Collection
    {
        $chunksDirectory = sprintf('bulk_assessments/%s/chunks', $this->jobId);

        return collect(Storage::disk($storageDisk)->files($chunksDirectory))
            ->filter(fn (string $path): bool => str_ends_with($path, '.json'))
            ->sort()
            ->values()
            ->map(function (string $path) use ($storageDisk): array {
                $contents = Storage::disk($storageDisk)->get($path);

                $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

                return is_array($decoded) ? $decoded : [];
            })
            ->filter(fn (array $state): bool => $state !== [])
            ->values();
    }

    private function downloadChunkToLocalPath(string $storageDisk, string $storagePath): string
    {
        $localChunkBasePath = tempnam(sys_get_temp_dir(), 'bulk_assessment_chunk_');

        if ($localChunkBasePath === false) {
            throw new Exception(sprintf('Failed to allocate local temp path for [%s].', $storagePath));
        }

        $localChunkPath = $localChunkBasePath.'.pdf';
        rename($localChunkBasePath, $localChunkPath);

        $sourceStream = Storage::disk($storageDisk)->readStream($storagePath);

        if ($sourceStream === false) {
            throw new Exception(sprintf('Unable to open storage stream for [%s].', $storagePath));
        }

        $targetStream = fopen($localChunkPath, 'wb');

        if ($targetStream === false) {
            fclose($sourceStream);

            throw new Exception(sprintf('Unable to open local write stream for [%s].', $localChunkPath));
        }

        try {
            stream_copy_to_stream($sourceStream, $targetStream);
        } finally {
            fclose($sourceStream);
            fclose($targetStream);
        }

        return $localChunkPath;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $chunkStates
     * @return array<int, array{id: int, name: string, reason: string}>
     */
    private function collectSkippedStudents(Collection $chunkStates): array
    {
        return $chunkStates
            ->flatMap(function (array $state): array {
                $skipped = $state['skipped'] ?? [];

                return is_array($skipped) ? $skipped : [];
            })
            ->filter(fn (mixed $entry): bool => is_array($entry))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{id: int, name: string, reason: string}>  $skippedStudents
     */
    private function storeSkippedStudentsReport(string $storageDisk, array $skippedStudents): ?string
    {
        if ($skippedStudents === []) {
            return null;
        }

        $reportPath = sprintf('bulk_assessments/%s/skipped_students.txt', $this->jobId);

        $content = "Skipped Students Report\n";
        $content .= sprintf("Job ID: %s\n", $this->jobId);
        $content .= sprintf("Generated At: %s\n\n", now()->toDateTimeString());

        foreach ($skippedStudents as $skippedStudent) {
            $content .= sprintf(
                "Enrollment #%d | %s | %s\n",
                (int) ($skippedStudent['id'] ?? 0),
                (string) ($skippedStudent['name'] ?? 'Unknown'),
                (string) ($skippedStudent['reason'] ?? 'Unknown reason')
            );
        }

        Storage::disk($storageDisk)->put($reportPath, $content, ['visibility' => 'public']);

        return Storage::disk($storageDisk)->url($reportPath);
    }

    private function notifySuccess(string $downloadUrl, int $successCount, ?string $reportUrl): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        $notification = Notification::make()
            ->title('Bulk Assessment PDF Ready')
            ->body(sprintf('Generated %d assessments successfully. Your merged PDF is ready to download.', $successCount))
            ->success()
            ->actions([
                Action::make('download')
                    ->label('Download PDF')
                    ->url($downloadUrl)
                    ->openUrlInNewTab(),
            ]);

        if ($reportUrl !== null) {
            $notification->actions([
                Action::make('download')
                    ->label('Download PDF')
                    ->url($downloadUrl)
                    ->openUrlInNewTab(),
                Action::make('skipped')
                    ->label('Skipped Students Report')
                    ->url($reportUrl)
                    ->openUrlInNewTab(),
            ]);
        }

        $notification->sendToDatabase($user);
    }

    private function notifyFailure(int $failedChunksCount): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        $message = $failedChunksCount > 0
            ? sprintf('Failed chunks: %d. Retry failed jobs in batch %s to continue.', $failedChunksCount, $this->batchId)
            : 'Chunk merge failed. Please retry the failed batch jobs.';

        Notification::make()
            ->title('Bulk Assessment Generation Failed')
            ->body($message)
            ->danger()
            ->sendToDatabase($user);
    }
}
