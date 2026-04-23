<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\StudentEnrollment;
use App\Services\GeneralSettingsService;
use App\Services\PdfGenerationService;
use App\Support\StreamedStorage;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class GenerateBulkAssessmentChunkJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public int $tries = 3;

    /**
     * @param  array<int, int>  $enrollmentIds
     */
    public function __construct(
        public string $jobId,
        public int $chunkIndex,
        public array $enrollmentIds,
    ) {
        $this->onQueue('pdf-generation');
    }

    public function handle(PdfGenerationService $pdfService, GeneralSettingsService $settingsService): void
    {
        $storageDisk = (string) config('filesystems.default');
        $tempDirectory = null;
        $mergedChunkPath = null;
        $generatedCount = 0;
        $skipped = [];

        try {
            $enrollments = StudentEnrollment::query()
                ->withTrashed()
                ->whereIn('id', $this->enrollmentIds)
                ->with(['student', 'course', 'subjectsEnrolled.subject', 'studentTuition'])
                ->get()
                ->keyBy('id');

            $tempDirectory = $pdfService->createTempDirectory(sprintf('bulk_assessment_chunk_%s_%d_', $this->jobId, $this->chunkIndex));

            $individualPdfPaths = [];

            foreach ($this->enrollmentIds as $position => $enrollmentId) {
                /** @var StudentEnrollment|null $enrollment */
                $enrollment = $enrollments->get($enrollmentId);

                if ($enrollment === null || $enrollment->student === null || $enrollment->course === null) {
                    $skipped[] = [
                        'id' => $enrollmentId,
                        'name' => $enrollment?->student?->student_name ?? 'Unknown',
                        'reason' => 'Missing student/course record',
                    ];

                    continue;
                }

                $individualPdfPaths[] = $this->generateIndividualPdf(
                    enrollment: $enrollment,
                    settingsService: $settingsService,
                    pdfService: $pdfService,
                    tempDirectory: $tempDirectory,
                    index: $position,
                );

                $generatedCount++;
            }

            if ($individualPdfPaths === []) {
                throw new Exception(sprintf('Chunk %d did not produce any valid PDFs.', $this->chunkIndex));
            }

            $mergedChunkBasePath = tempnam(sys_get_temp_dir(), sprintf('bulk_chunk_%d_', $this->chunkIndex));

            if ($mergedChunkBasePath === false) {
                throw new Exception('Failed to allocate temporary file for merged chunk PDF.');
            }

            $mergedChunkPath = $mergedChunkBasePath.'.pdf';
            rename($mergedChunkBasePath, $mergedChunkPath);

            $pdfService->mergePdfs($individualPdfPaths, $mergedChunkPath, true);

            $storagePath = sprintf('bulk_assessments/%s/chunks/chunk-%03d.pdf', $this->jobId, $this->chunkIndex);

            StreamedStorage::putFileFromPath($storageDisk, $storagePath, $mergedChunkPath, ['visibility' => 'public']);

            $this->writeChunkState($storageDisk, [
                'job_id' => $this->jobId,
                'chunk_index' => $this->chunkIndex,
                'status' => 'completed',
                'storage_path' => $storagePath,
                'enrollment_ids' => $this->enrollmentIds,
                'generated_count' => $generatedCount,
                'skipped' => $skipped,
                'updated_at' => format_timestamp_now(),
            ]);
        } catch (Throwable $throwable) {
            Log::error('Bulk assessment chunk generation failed', [
                'job_id' => $this->jobId,
                'chunk_index' => $this->chunkIndex,
                'error' => $throwable->getMessage(),
            ]);

            $this->writeChunkState($storageDisk, [
                'job_id' => $this->jobId,
                'chunk_index' => $this->chunkIndex,
                'status' => 'failed',
                'error' => $throwable->getMessage(),
                'enrollment_ids' => $this->enrollmentIds,
                'generated_count' => $generatedCount,
                'skipped' => $skipped,
                'updated_at' => format_timestamp_now(),
            ]);

            throw $throwable;
        } finally {
            if ($tempDirectory !== null) {
                $pdfService->cleanupTempDirectory($tempDirectory);
            }

            if ($mergedChunkPath !== null && file_exists($mergedChunkPath)) {
                unlink($mergedChunkPath);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $chunkState
     */
    private function writeChunkState(string $storageDisk, array $chunkState): void
    {
        $chunkStatePath = sprintf('bulk_assessments/%s/chunks/chunk-%03d.json', $this->jobId, $this->chunkIndex);

        Storage::disk($storageDisk)->put(
            $chunkStatePath,
            json_encode($chunkState, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            ['visibility' => 'private']
        );
    }

    private function generateIndividualPdf(
        StudentEnrollment $enrollment,
        GeneralSettingsService $settingsService,
        PdfGenerationService $pdfService,
        string $tempDirectory,
        int $index,
    ): string {
        $data = [
            'student' => $enrollment,
            'subjects' => $enrollment->SubjectsEnrolled,
            'school_year' => mb_convert_encoding(
                $settingsService->getCurrentSchoolYearString() ?? '',
                'UTF-8',
                'auto'
            ),
            'semester' => mb_convert_encoding(
                $settingsService->getAvailableSemesters()[$settingsService->getCurrentSemester()] ?? '',
                'UTF-8',
                'auto'
            ),
            'tuition' => $enrollment->studentTuition,
            'general_settings' => $settingsService->getGlobalSettingsModel(),
            'siteSettings' => app(\App\Settings\SiteSettings::class)->getBrandingArray(),
        ];

        $safeStudentLastName = preg_replace('/[^a-zA-Z0-9]/', '_', mb_substr($enrollment->student->last_name ?? 'unknown', 0, 20));

        $individualPdfPath = sprintf(
            '%s%s%05d_%s_%s.pdf',
            $tempDirectory,
            DIRECTORY_SEPARATOR,
            $index,
            $enrollment->id,
            $safeStudentLastName
        );

        $pdfService->generatePdfFromView('pdf.assesment-form', $data, $individualPdfPath, [], 'assessment_form');

        if (! file_exists($individualPdfPath) || filesize($individualPdfPath) === 0) {
            throw new Exception(sprintf('Failed to generate PDF for enrollment %d.', $enrollment->id));
        }

        return $individualPdfPath;
    }
}
