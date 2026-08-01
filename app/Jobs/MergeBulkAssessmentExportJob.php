<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AssessmentExport;
use App\Services\AssessmentExportArtifactService;
use App\Services\AssessmentExportCoordinator;
use App\Services\AssessmentExportNotificationService;
use App\Services\PdfGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class MergeBulkAssessmentExportJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout;

    public int $tries;

    public bool $failOnTimeout = true;

    public function __construct(public string $exportId)
    {
        $this->timeout = (int) config('assessment-exports.merge.timeout');
        $this->tries = (int) config('assessment-exports.merge.tries');
        $this->onConnection((string) config('assessment-exports.connection'));
        $this->onQueue((string) config('assessment-exports.render_queue'));
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return (array) config('assessment-exports.merge.backoff', [60, 300]);
    }

    public function uniqueId(): string
    {
        return 'merge-'.$this->exportId;
    }

    public function handle(
        PdfGenerationService $pdfs,
        AssessmentExportArtifactService $artifacts,
        AssessmentExportCoordinator $coordinator,
        AssessmentExportNotificationService $notifications,
    ): void {
        $export = AssessmentExport::withoutSchoolScope()->findOrFail($this->exportId);
        if ($export->status === 'completed') {
            return;
        }
        $this->guardPublishable($export);

        $items = $export->items()
            ->withoutSchoolScope()
            ->forSchool((int) $export->school_id)
            ->where('status', 'completed')
            ->orderBy('sequence')
            ->get(['id', 'sequence', 'artifact_disk', 'artifact_path', 'page_count', 'checksum']);
        if ($items->count() !== $export->completed_count) {
            throw new RuntimeException('The rendered assessment set is incomplete and cannot be merged.');
        }

        $disk = (string) config('assessment-exports.disk');
        if ($items->isEmpty()) {
            $reportPath = $this->storeSkippedReport($export, $disk);
            if ($export->skipped_count > 0 && $reportPath === null) {
                throw new RuntimeException('The skipped-student report could not be created.');
            }

            DB::transaction(function () use ($export, $disk, $reportPath): void {
                $locked = AssessmentExport::withoutSchoolScope()
                    ->forSchool((int) $export->school_id)
                    ->lockForUpdate()
                    ->findOrFail($export->id);
                $this->guardPublishable($locked);
                $locked->forceFill([
                    'status' => 'completed',
                    'stage' => 'no_matches',
                    'percentage' => 100,
                    'message' => sprintf('No valid assessment forms were generated. %d enrollment(s) were skipped.', $locked->skipped_count),
                    'output_disk' => $disk,
                    'output_path' => null,
                    'report_path' => $reportPath,
                    'completed_at' => now(),
                    'failed_at' => null,
                ])->save();
            });

            $export->refresh();
            $coordinator->broadcast($export);
            $notifications->sendTerminal($export);

            return;
        }

        $fanIn = max(2, (int) config('assessment-exports.merge.fan_in', 20));
        $groups = $items->chunk($fanIn)->values();
        $export->forceFill([
            'status' => 'processing',
            'stage' => 'merging',
            'merged_parts' => 0,
            'total_parts' => $groups->count(),
            'percentage' => 85,
            'message' => sprintf('Merging 0 of %d PDF parts...', $groups->count()),
        ])->save();
        $coordinator->broadcast($export->refresh());

        $partPaths = [];
        $expectedPages = 0;
        foreach ($groups as $groupIndex => $group) {
            $export->refresh();
            $this->guardPublishable($export);

            $partStoragePath = sprintf(
                'assessment-exports/%d/%d/%s/parts/%04d.pdf',
                $export->school_id,
                $export->user_id,
                $export->id,
                $groupIndex + 1,
            );
            $groupExpectedPages = (int) $group->sum('page_count');
            $expectedPages += $groupExpectedPages;

            if (! $this->storedPartIsValid($artifacts, $disk, $partStoragePath, $groupExpectedPages)) {
                Storage::disk($disk)->delete($partStoragePath);
                $this->buildPart($group->all(), $partStoragePath, $disk, $pdfs, $artifacts);
            }
            $partPaths[] = $partStoragePath;

            $mergedParts = $groupIndex + 1;
            $percentage = 85 + (int) floor(($mergedParts / max(1, $groups->count())) * 10);
            $export->forceFill([
                'merged_parts' => $mergedParts,
                'percentage' => min(95, $percentage),
                'message' => sprintf('Merged %d of %d PDF parts...', $mergedParts, $groups->count()),
            ])->save();
            $coordinator->broadcast($export->refresh());
        }

        $this->guardPublishable($export->refresh());
        $temporaryDirectory = $pdfs->createTempDirectory('assessment_export_final_');
        try {
            $localParts = [];
            foreach ($partPaths as $partPath) {
                $localParts[] = $artifacts->downloadToTemporaryPath($disk, $partPath);
            }

            $finalLocalPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'bulk-assessments.pdf';
            $pdfs->mergePdfsChunked($localParts, $finalLocalPath, $fanIn, true);
            $metadata = $artifacts->validateLocalPdf($finalLocalPath);
            if ($metadata['page_count'] !== $expectedPages) {
                throw new RuntimeException(sprintf('Final PDF page count mismatch: expected %d, got %d.', $expectedPages, $metadata['page_count']));
            }

            $this->guardPublishable($export->refresh());
            $finalStoragePath = sprintf(
                'assessment-exports/%d/%d/%s/final/bulk-assessments.pdf',
                $export->school_id,
                $export->user_id,
                $export->id,
            );
            $storedMetadata = $artifacts->storeValidatedPdf($disk, $finalStoragePath, $finalLocalPath);
            if ($storedMetadata['page_count'] !== $expectedPages) {
                Storage::disk($disk)->delete($finalStoragePath);
                throw new RuntimeException('Stored final PDF did not preserve the expected page count.');
            }

            $reportPath = $this->storeSkippedReport($export, $disk);
            DB::transaction(function () use ($export, $disk, $finalStoragePath, $reportPath): void {
                $locked = AssessmentExport::withoutSchoolScope()
                    ->forSchool((int) $export->school_id)
                    ->lockForUpdate()
                    ->findOrFail($export->id);
                $this->guardPublishable($locked);
                $locked->forceFill([
                    'status' => 'completed',
                    'stage' => 'ready',
                    'percentage' => 100,
                    'message' => sprintf(
                        'Generated %d assessments successfully%s.',
                        $locked->completed_count,
                        $locked->skipped_count > 0 ? sprintf(' (%d skipped)', $locked->skipped_count) : '',
                    ),
                    'output_disk' => $disk,
                    'output_path' => $finalStoragePath,
                    'output_name' => sprintf('bulk-assessments-%s.pdf', now()->format('Y-m-d-His')),
                    'report_path' => $reportPath,
                    'error_code' => null,
                    'error_message' => null,
                    'error_context' => null,
                    'completed_at' => now(),
                    'failed_at' => null,
                ])->save();
            });

            $export->refresh();
            $coordinator->broadcast($export);
            $notifications->sendTerminal($export);
        } finally {
            foreach ($localParts ?? [] as $localPart) {
                @unlink($localPart);
            }
            $pdfs->cleanupTempDirectory($temporaryDirectory);
        }
    }

    public function failed(?Throwable $throwable): void
    {
        if ($throwable !== null) {
            app(AssessmentExportCoordinator::class)->fail($this->exportId, 'merging', $throwable);
        }
    }

    /** @param array<int, object> $items */
    private function buildPart(
        array $items,
        string $partStoragePath,
        string $disk,
        PdfGenerationService $pdfs,
        AssessmentExportArtifactService $artifacts,
    ): void {
        $temporaryDirectory = $pdfs->createTempDirectory('assessment_export_part_');
        $localItems = [];
        try {
            foreach ($items as $item) {
                if ($item->artifact_disk !== $disk || $item->artifact_path === null) {
                    throw new RuntimeException(sprintf('Assessment artifact metadata is invalid for item %d.', $item->id));
                }
                $localPath = $artifacts->downloadToTemporaryPath($disk, $item->artifact_path);
                $metadata = $artifacts->validateLocalPdf($localPath);
                if ($metadata['page_count'] !== (int) $item->page_count || $metadata['checksum'] !== $item->checksum) {
                    throw new RuntimeException(sprintf('Assessment artifact validation failed for item %d.', $item->id));
                }
                $localItems[] = $localPath;
            }

            $localPart = $temporaryDirectory.DIRECTORY_SEPARATOR.'part.pdf';
            $pdfs->mergePdfs($localItems, $localPart, true);
            $artifacts->storeValidatedPdf($disk, $partStoragePath, $localPart);
        } finally {
            foreach ($localItems as $localItem) {
                @unlink($localItem);
            }
            $pdfs->cleanupTempDirectory($temporaryDirectory);
        }
    }

    private function storedPartIsValid(
        AssessmentExportArtifactService $artifacts,
        string $disk,
        string $path,
        int $expectedPages,
    ): bool {
        if (! Storage::disk($disk)->exists($path)) {
            return false;
        }

        $localPath = null;
        try {
            $localPath = $artifacts->downloadToTemporaryPath($disk, $path);

            return $artifacts->validateLocalPdf($localPath)['page_count'] === $expectedPages;
        } catch (Throwable $throwable) {
            Log::warning('Discarding invalid assessment export merge part', [
                'export_id' => $this->exportId,
                'path' => $path,
                'exception' => $throwable,
            ]);

            return false;
        } finally {
            if ($localPath !== null) {
                @unlink($localPath);
            }
        }
    }

    private function guardPublishable(AssessmentExport $export): void
    {
        if ($export->cancel_requested_at !== null || in_array($export->status, ['cancelling', 'cancelled'], true)) {
            throw new RuntimeException('Assessment export was cancelled before publication.');
        }
        if ($export->failed_count > 0 || $export->processed_count !== $export->total_count) {
            throw new RuntimeException('Assessment export cannot be published while items are incomplete or failed.');
        }
    }

    private function storeSkippedReport(AssessmentExport $export, string $disk): ?string
    {
        if ($export->skipped_count === 0) {
            return null;
        }

        $path = sprintf(
            'assessment-exports/%d/%d/%s/final/skipped-assessments.csv',
            $export->school_id,
            $export->user_id,
            $export->id,
        );
        $lines = ['sequence,enrollment_id,reason'];
        $export->items()
            ->withoutSchoolScope()
            ->forSchool((int) $export->school_id)
            ->where('status', 'skipped')
            ->orderBy('sequence')
            ->each(function ($item) use (&$lines): void {
                $lines[] = sprintf('%d,%d,"%s"', $item->sequence, $item->enrollment_id, str_replace('"', '""', (string) $item->error_message));
            });
        $written = Storage::disk($disk)->put($path, implode("\n", $lines)."\n", ['visibility' => 'private']);
        if (! $written || ! Storage::disk($disk)->exists($path)) {
            throw new RuntimeException('Unable to store the skipped-student report.');
        }

        return $path;
    }
}
