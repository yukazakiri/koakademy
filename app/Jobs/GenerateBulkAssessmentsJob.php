<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\StudentEnrollment;
use App\Services\EnrollmentPipelineService;
use App\Services\GeneralSettingsService;
use App\Services\JobTrackerService;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class GenerateBulkAssessmentsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const int CHUNK_SIZE = 25;

    public int $timeout = 300;

    public int $tries = 2;

    private string $jobId;

    /**
     * Create a new job instance.
     *
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        private array $filters,
        private int $userId,
        ?string $jobId = null
    ) {
        $this->jobId = $jobId ?? uniqid('bulk_assessment_', true);
        $this->onQueue('pdf-generation');
    }

    public function handle(JobTrackerService $jobTracker): void
    {
        $jobTracker->registerJob(
            $this->jobId,
            $this->userId,
            'bulk_assessment',
            'Bulk Assessment Export',
            ['filters' => $this->filters]
        );

        try {
            $enrollments = $this->getFilteredAndSortedEnrollments();

            if ($enrollments->isEmpty()) {
                $jobTracker->markCompleted($this->jobId, 'No enrollments found matching criteria.');

                return;
            }

            [$validEnrollmentIds, $skippedEnrollments] = $this->partitionEnrollments($enrollments);

            if ($validEnrollmentIds === []) {
                $manifestPath = $this->storeBatchManifest([], $skippedEnrollments, null);

                $jobTracker->markCompleted(
                    $this->jobId,
                    'No valid enrollments were found after validation checks.',
                    metadata: [
                        'manifest_path' => $manifestPath,
                        'skipped_count' => count($skippedEnrollments),
                    ]
                );

                return;
            }

            $chunks = array_values(array_chunk($validEnrollmentIds, self::CHUNK_SIZE));
            $manifestPath = $this->storeBatchManifest($chunks, $skippedEnrollments, null);

            $chunkJobs = [];
            foreach ($chunks as $chunkIndex => $chunkEnrollmentIds) {
                $chunkJobs[] = new GenerateBulkAssessmentChunkJob(
                    jobId: $this->jobId,
                    chunkIndex: $chunkIndex,
                    enrollmentIds: $chunkEnrollmentIds,
                );
            }

            $jobId = $this->jobId;
            $userId = $this->userId;
            $totalChunks = count($chunkJobs);

            $jobTracker->updateProgress(
                $jobId,
                15,
                sprintf('Queued %d chunk jobs for PDF generation...', $totalChunks),
                metadata: [
                    'total_chunks' => $totalChunks,
                    'skipped_count' => count($skippedEnrollments),
                    'manifest_path' => $manifestPath,
                ]
            );

            $batch = Bus::batch($chunkJobs)
                ->name('bulk-assessment-'.$jobId)
                ->allowFailures()
                ->onQueue('pdf-generation')
                ->progress(function (Batch $batch) use ($jobId, $totalChunks, $manifestPath): void {
                    $processedChunks = $batch->processedJobs();
                    $percentage = 20 + (int) floor(($processedChunks / max(1, $totalChunks)) * 55);

                    app(JobTrackerService::class)->updateProgress(
                        $jobId,
                        $percentage,
                        sprintf('Generated %d of %d chunks...', $processedChunks, $totalChunks),
                        metadata: [
                            'batch_id' => $batch->id,
                            'processed_chunks' => $processedChunks,
                            'total_chunks' => $totalChunks,
                            'failed_chunks' => $batch->failedJobs,
                            'manifest_path' => $manifestPath,
                        ]
                    );
                })
                ->catch(function (Batch $batch, Throwable $throwable) use ($jobId): void {
                    Log::warning('Bulk assessment chunk batch encountered a failing chunk', [
                        'job_id' => $jobId,
                        'batch_id' => $batch->id,
                        'error' => $throwable->getMessage(),
                    ]);
                })
                ->finally(function (Batch $batch) use ($jobId, $userId, $manifestPath): void {
                    $tracker = app(JobTrackerService::class);

                    if ($batch->failedJobs > 0) {
                        $tracker->markFailed(
                            $jobId,
                            sprintf('Chunk generation failed for %d chunk(s). Retry failed batch jobs only.', $batch->failedJobs)
                        );

                        $tracker->updateProgress(
                            $jobId,
                            80,
                            'Chunk generation finished with failures. Retry failed chunks to continue.',
                            'failed',
                            [
                                'batch_id' => $batch->id,
                                'failed_chunks' => $batch->failedJobs,
                                'manifest_path' => $manifestPath,
                            ]
                        );

                        return;
                    }

                    $tracker->updateProgress(
                        $jobId,
                        80,
                        'All chunks generated. Starting merge phase...',
                        metadata: [
                            'batch_id' => $batch->id,
                            'manifest_path' => $manifestPath,
                        ]
                    );

                    MergeBulkAssessmentChunksJob::dispatch(
                        jobId: $jobId,
                        userId: $userId,
                        batchId: $batch->id,
                        manifestPath: $manifestPath,
                    )->onQueue('pdf-generation');
                })
                ->dispatch();

            $this->storeBatchManifest($chunks, $skippedEnrollments, $batch->id);

            $jobTracker->updateProgress(
                $jobId,
                20,
                'Chunk jobs dispatched successfully.',
                metadata: [
                    'batch_id' => $batch->id,
                    'total_chunks' => $totalChunks,
                    'manifest_path' => $manifestPath,
                    'skipped_count' => count($skippedEnrollments),
                ]
            );
        } catch (Throwable $throwable) {
            Log::error('Failed to dispatch bulk assessment chunk pipeline', [
                'job_id' => $this->jobId,
                'error' => $throwable->getMessage(),
            ]);

            $jobTracker->markFailed($this->jobId, $throwable->getMessage());

            throw $throwable;
        }
    }

    /**
     * @return Collection<int, StudentEnrollment>
     */
    private function getFilteredAndSortedEnrollments(): Collection
    {
        $settingsService = app(GeneralSettingsService::class);
        $pipelineService = app(EnrollmentPipelineService::class);

        $currentSchoolYear = (string) ($this->filters['school_year'] ?? $settingsService->getCurrentSchoolYearString());
        $currentSemester = (int) ($this->filters['semester'] ?? $settingsService->getCurrentSemester());

        $query = StudentEnrollment::query()
            ->where('school_year', $currentSchoolYear)
            ->where('semester', $currentSemester)
            ->where('status', $pipelineService->getCashierVerifiedStatus())
            ->with(['student', 'course', 'subjectsEnrolled.subject', 'studentTuition']);

        if (($this->filters['include_deleted'] ?? false) === true) {
            $query->withTrashed();
        }

        if (isset($this->filters['course_filter']) && $this->filters['course_filter'] !== 'all') {
            $query->whereExists(function ($subQuery): void {
                $subQuery->select(DB::raw(1))
                    ->from('courses')
                    ->whereRaw('CAST(student_enrollment.course_id AS BIGINT) = courses.id')
                    ->where('courses.code', 'LIKE', $this->filters['course_filter'].'%');
            });
        }

        if (isset($this->filters['year_level_filter']) && $this->filters['year_level_filter'] !== 'all') {
            $query->where('academic_year', (int) $this->filters['year_level_filter']);
        }

        if (isset($this->filters['student_limit']) && $this->filters['student_limit'] !== 'all') {
            $query->limit((int) $this->filters['student_limit']);
        }

        return $query->get()->sortBy([
            fn (StudentEnrollment $left, StudentEnrollment $right): int => ($left->course?->code ?? '') <=> ($right->course?->code ?? ''),
            fn (StudentEnrollment $left, StudentEnrollment $right): int => ($left->academic_year ?? 0) <=> ($right->academic_year ?? 0),
            fn (StudentEnrollment $left, StudentEnrollment $right): int => ($left->student?->last_name ?? '') <=> ($right->student?->last_name ?? ''),
        ])->values();
    }

    /**
     * @param  Collection<int, StudentEnrollment>  $enrollments
     * @return array{0: array<int, int>, 1: array<int, array{id: int, name: string, reason: string}>}
     */
    private function partitionEnrollments(Collection $enrollments): array
    {
        $validEnrollmentIds = [];
        $skippedEnrollments = [];

        foreach ($enrollments as $enrollment) {
            if ($enrollment->student === null || $enrollment->course === null) {
                $reasons = [];

                if ($enrollment->student === null) {
                    $reasons[] = 'Missing student record';
                }

                if ($enrollment->course === null) {
                    $reasons[] = 'Missing course record';
                }

                $skippedEnrollments[] = [
                    'id' => $enrollment->id,
                    'name' => $enrollment->student?->student_name ?? 'Unknown',
                    'reason' => implode(', ', $reasons),
                ];

                continue;
            }

            $validEnrollmentIds[] = $enrollment->id;
        }

        return [$validEnrollmentIds, $skippedEnrollments];
    }

    /**
     * @param  array<int, array<int, int>>  $chunks
     * @param  array<int, array{id: int, name: string, reason: string}>  $skippedEnrollments
     */
    private function storeBatchManifest(array $chunks, array $skippedEnrollments, ?string $batchId): string
    {
        $storageDisk = (string) config('filesystems.default');
        $baseDirectory = sprintf('bulk_assessments/%s', $this->jobId);
        $chunksDirectory = $baseDirectory.'/chunks';
        $manifestPath = $baseDirectory.'/manifest.json';

        Storage::disk($storageDisk)->makeDirectory($chunksDirectory);

        $manifest = [
            'job_id' => $this->jobId,
            'batch_id' => $batchId,
            'user_id' => $this->userId,
            'filters' => $this->filters,
            'chunk_size' => self::CHUNK_SIZE,
            'total_chunks' => count($chunks),
            'chunks' => array_map(
                fn (array $chunkEnrollmentIds, int $chunkIndex): array => [
                    'chunk_index' => $chunkIndex,
                    'enrollment_ids' => $chunkEnrollmentIds,
                    'status' => 'pending',
                ],
                $chunks,
                array_keys($chunks)
            ),
            'skipped_enrollments' => $skippedEnrollments,
            'created_at' => format_timestamp_now(),
            'updated_at' => format_timestamp_now(),
        ];

        Storage::disk($storageDisk)->put(
            $manifestPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            ['visibility' => 'private']
        );

        return $manifestPath;
    }
}
