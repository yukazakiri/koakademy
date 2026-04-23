<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\EnrollmentPipelineService;
use App\Services\GeneralSettingsService;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class GenerateBulkAssessmentsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800; // 30 minutes timeout for bulk operations

    public int $tries = 2;

    private string $jobId;

    private array $skippedStudents = [];

    /**
     * Create a new job instance.
     */
    public function __construct(
        private array $filters,
        private int $userId,
        ?string $jobId = null
    ) {
        $this->jobId = $jobId ?? uniqid('bulk_assessment_', true);

        // Use default queue for bulk operations
        $this->onQueue('default');

        Log::info('GenerateBulkAssessmentsJob created', [
            'job_id' => $this->jobId,
            'filters' => $this->filters,
            'user_id' => $this->userId,
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(JobTrackerService $jobTracker): void
    {
        try {
            // Register job with tracker
            $jobTracker->registerJob(
                $this->jobId,
                $this->userId,
                'bulk_assessment',
                'Bulk Assessment Export',
                ['filters' => $this->filters]
            );

            Log::info('Starting bulk assessment generation', [
                'job_id' => $this->jobId,
                'filters' => $this->filters,
            ]);

            $jobTracker->updateProgress($this->jobId, 10, 'Fetching enrollments...');

            // Get filtered and sorted enrollments
            $enrollments = $this->getFilteredAndSortedEnrollments();

            if ($enrollments->isEmpty()) {
                $jobTracker->markCompleted($this->jobId, 'No enrollments found matching criteria');
                $this->sendNotification(
                    'No Enrollments Found',
                    'No enrollments match the selected criteria.',
                    'warning'
                );

                return;
            }

            Log::info('Found enrollments for bulk generation', [
                'job_id' => $this->jobId,
                'count' => $enrollments->count(),
            ]);

            $totalCount = $enrollments->count();

            $jobTracker->updateProgress(
                $this->jobId,
                30,
                sprintf('Preparing to generate %d assessments...', $totalCount),
                'processing',
                [
                    'processed_count' => 0,
                    'total_count' => $totalCount,
                ]
            );

            // Generate combined PDF
            $pdfPath = $this->generateCombinedAssessmentPdf($enrollments, $jobTracker);

            $reportUrl = null;
            $storageDisk = config('filesystems.default');

            if ($this->skippedStudents !== []) {
                $reportContent = "Skipped Students Report - Bulk Assessment Generation\n";
                $reportContent .= "Job ID: {$this->jobId}\n";
                $reportContent .= 'Date: '.now()->toDateTimeString()."\n\n";
                $reportContent .= mb_str_pad('Enrollment ID', 15).mb_str_pad('Student Name', 40)."Reason\n";
                $reportContent .= str_repeat('-', 80)."\n";

                foreach ($this->skippedStudents as $skipped) {
                    $reportContent .= mb_str_pad((string) $skipped['id'], 15)
                        .mb_str_pad(mb_substr((string) $skipped['name'], 0, 38), 40)
                        .$skipped['reason']."\n";
                }

                $reportFileName = 'bulk_assessments/skipped_students_'.$this->jobId.'.txt';
                Storage::disk($storageDisk)->put($reportFileName, $reportContent, ['visibility' => 'public']);
                $reportUrl = Storage::disk($storageDisk)->url($reportFileName);
            }

            if ($pdfPath && file_exists($pdfPath)) {
                // Upload the locally generated PDF to the configured storage disk
                $pdfFileName = 'bulk_assessments/'.basename($pdfPath);

                try {
                    StreamedStorage::putFileFromPath($storageDisk, $pdfFileName, $pdfPath, ['visibility' => 'public']);
                } finally {
                    // Clean up local file after upload attempt
                    if (file_exists($pdfPath)) {
                        unlink($pdfPath);
                    }
                }

                $downloadUrl = Storage::disk($storageDisk)->url($pdfFileName);

                $successCount = $enrollments->count() - count($this->skippedStudents);
                $message = sprintf('Generated %d assessments successfully', $successCount);

                if ($this->skippedStudents !== []) {
                    $message .= sprintf(' (%d skipped)', count($this->skippedStudents));
                }

                $jobTracker->markCompleted(
                    $this->jobId,
                    $message,
                    $downloadUrl,
                    ['report_url' => $reportUrl]
                );

                $this->sendSuccessNotification($downloadUrl, $successCount);
            } else {
                throw new Exception('Failed to generate combined assessment PDF');
            }

        } catch (Exception $exception) {
            Log::error('Bulk assessment generation failed', [
                'job_id' => $this->jobId,
                'exception' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            $jobTracker->markFailed($this->jobId, $exception->getMessage());

            $this->sendNotification(
                'Assessment Generation Failed',
                'Error: '.$exception->getMessage(),
                'danger'
            );

            throw $exception;
        }
    }

    /**
     * Get filtered enrollments sorted by Course Code → Year Level → Last Name
     */
    private function getFilteredAndSortedEnrollments()
    {
        $generalSettingsService = app(GeneralSettingsService::class);

        // Use passed filters for context-aware settings if available, otherwise fallback
        $currentSchoolYear = $this->filters['school_year'] ?? $generalSettingsService->getCurrentSchoolYearString();
        $currentSemester = $this->filters['semester'] ?? $generalSettingsService->getCurrentSemester();

        $builder = StudentEnrollment::query()->where('school_year', $currentSchoolYear)
            ->where('semester', $currentSemester)
            ->where('status', app(EnrollmentPipelineService::class)->getCashierVerifiedStatus())
            ->with(['student', 'course', 'subjectsEnrolled.subject', 'studentTuition']);

        // Include deleted records if requested
        if ($this->filters['include_deleted'] ?? true) {
            $builder->withTrashed();
        }

        // Apply course filter with proper type casting
        if (isset($this->filters['course_filter']) && $this->filters['course_filter'] !== 'all') {
            $builder->whereExists(function ($subQuery): void {
                $subQuery->select(DB::raw(1))
                    ->from('courses')
                    ->whereRaw('CAST(student_enrollment.course_id AS BIGINT) = courses.id')
                    ->where('courses.code', 'LIKE', $this->filters['course_filter'].'%');
            });
        }

        // Apply year level filter
        if (isset($this->filters['year_level_filter']) && $this->filters['year_level_filter'] !== 'all') {
            $builder->where('academic_year', $this->filters['year_level_filter']);
        }

        // Apply student limit
        if (isset($this->filters['student_limit']) && $this->filters['student_limit'] !== 'all') {
            $builder->limit((int) $this->filters['student_limit']);
        }

        // Get enrollments and sort by Course Code → Year Level → Last Name
        $enrollments = $builder->get();

        return $enrollments->sortBy([
            fn ($a, $b): int => ($a->course?->code ?? '') <=> ($b->course?->code ?? ''),
            fn ($a, $b): int => ($a->academic_year ?? 0) <=> ($b->academic_year ?? 0),
            fn ($a, $b): int => ($a->student?->last_name ?? '') <=> ($b->student?->last_name ?? ''),
        ])->values(); // Reset array keys after sorting
    }

    /**
     * Generate combined assessment PDF by creating individual PDFs and merging them
     */
    private function generateCombinedAssessmentPdf($enrollments, JobTrackerService $jobTracker): string
    {
        $pdfService = app(PdfGenerationService::class);
        $settingsService = app(GeneralSettingsService::class);
        $tempDir = null;

        try {
            $compiledPath = storage_path('app/public/bulk_assessments_'.date('Y-m-d_H-i-s').'.pdf');

            // Create temporary directory for individual PDFs
            $tempDir = $pdfService->createTempDirectory('bulk_assessment_');

            $totalStudents = $enrollments->count();
            $processedCount = 0;
            $individualPdfPaths = [];

            Log::info('Starting individual PDF generation', [
                'job_id' => $this->jobId,
                'total_students' => $totalStudents,
                'temp_dir' => $tempDir,
            ]);

            $jobTracker->updateProgress(
                $this->jobId,
                15,
                sprintf('Generating individual PDFs (0 of %d)...', $totalStudents),
                'processing',
                [
                    'processed_count' => 0,
                    'total_count' => $totalStudents,
                    'phase' => 'generating',
                ]
            );

            foreach ($enrollments as $index => $enrollment) {
                // Skip enrollments without valid student or course
                if ($enrollment->student === null || $enrollment->course === null) {
                    $reason = [];
                    if ($enrollment->student === null) {
                        $reason[] = 'Missing Student Record';
                    }
                    if ($enrollment->course === null) {
                        $reason[] = 'Missing Course Record';
                    }

                    Log::warning('Skipping enrollment with null student or course', [
                        'job_id' => $this->jobId,
                        'enrollment_id' => $enrollment->id,
                    ]);

                    $this->skippedStudents[] = [
                        'id' => $enrollment->id,
                        'name' => $enrollment->student?->student_name ?? 'Unknown',
                        'reason' => implode(', ', $reason),
                    ];

                    $processedCount++;

                    continue;
                }

                try {
                    // Generate individual PDF
                    $individualPdfPath = $this->generateIndividualPdf(
                        $enrollment,
                        $settingsService,
                        $pdfService,
                        $tempDir,
                        $index
                    );

                    $individualPdfPaths[] = $individualPdfPath;
                } catch (Exception $e) {
                    Log::error('Failed to generate individual PDF', [
                        'job_id' => $this->jobId,
                        'enrollment_id' => $enrollment->id,
                        'student_name' => $enrollment->student?->student_name ?? 'Unknown',
                        'error' => $e->getMessage(),
                    ]);

                    $this->skippedStudents[] = [
                        'id' => $enrollment->id,
                        'name' => $enrollment->student?->student_name ?? 'Unknown',
                        'reason' => 'PDF generation failed: '.$e->getMessage(),
                    ];
                }

                $processedCount++;

                // Update progress every 3 students or on the last one
                if ($processedCount % 3 === 0 || $processedCount === $totalStudents) {
                    $percentage = (int) (15 + (($processedCount / $totalStudents) * 65)); // 15% to 80%
                    $jobTracker->updateProgress(
                        $this->jobId,
                        $percentage,
                        sprintf('Generating PDF %d of %d...', $processedCount, $totalStudents),
                        'processing',
                        [
                            'processed_count' => $processedCount,
                            'total_count' => $totalStudents,
                            'phase' => 'generating',
                        ]
                    );
                }
            }

            if ($individualPdfPaths === []) {
                throw new Exception('No valid PDFs were generated - all students were skipped');
            }

            Log::info('Individual PDFs generated, starting merge', [
                'job_id' => $this->jobId,
                'pdf_count' => count($individualPdfPaths),
            ]);

            $jobTracker->updateProgress(
                $this->jobId,
                85,
                sprintf('Merging %d PDFs into final document...', count($individualPdfPaths)),
                'processing',
                [
                    'processed_count' => $processedCount,
                    'total_count' => $totalStudents,
                    'phase' => 'merging',
                ]
            );

            // Merge all individual PDFs into one
            $pdfService->mergePdfs($individualPdfPaths, $compiledPath, true);

            $jobTracker->updateProgress($this->jobId, 95, 'Finalizing PDF...');

            if (! file_exists($compiledPath) || filesize($compiledPath) === 0) {
                throw new Exception('Failed to generate merged PDF');
            }

            Log::info('Combined assessment PDF created successfully', [
                'job_id' => $this->jobId,
                'path' => $compiledPath,
                'enrollments_count' => $enrollments->count(),
                'successful_pdfs' => count($individualPdfPaths),
                'skipped_count' => count($this->skippedStudents),
                'file_size' => filesize($compiledPath),
            ]);

            return $compiledPath;

        } catch (Exception $exception) {
            Log::error('Combined PDF generation failed', [
                'job_id' => $this->jobId,
                'exception' => $exception->getMessage(),
                'enrollments_count' => $enrollments->count(),
            ]);
            throw $exception;
        } finally {
            // Always clean up temporary directory
            if ($tempDir !== null) {
                try {
                    $pdfService->cleanupTempDirectory($tempDir);
                } catch (Exception $cleanupException) {
                    Log::warning('Failed to cleanup temp directory', [
                        'job_id' => $this->jobId,
                        'temp_dir' => $tempDir,
                        'error' => $cleanupException->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Generate individual PDF for a single enrollment
     */
    private function generateIndividualPdf(
        $enrollment,
        GeneralSettingsService $settingsService,
        PdfGenerationService $pdfService,
        string $tempDir,
        int $index
    ): string {
        $generalSettings = $settingsService->getGlobalSettingsModel();

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
            'general_settings' => $generalSettings,
            'siteSettings' => app(\App\Settings\SiteSettings::class)->getBrandingArray(),
        ];

        // Generate unique filename using enrollment ID and index
        $safeFileName = sprintf(
            '%05d_%s_%s.pdf',
            $index,
            $enrollment->id,
            preg_replace('/[^a-zA-Z0-9]/', '_', mb_substr($enrollment->student->last_name ?? 'unknown', 0, 20))
        );
        $individualPdfPath = $tempDir.DIRECTORY_SEPARATOR.$safeFileName;

        // Generate PDF from view
        $pdfService->generatePdfFromView('pdf.assesment-form', $data, $individualPdfPath, [
            'landscape' => true,
            'print-background' => true,
        ]);

        if (! file_exists($individualPdfPath) || filesize($individualPdfPath) === 0) {
            throw new Exception("Failed to generate PDF for enrollment {$enrollment->id}");
        }

        return $individualPdfPath;
    }

    /**
     * Send success notification with download link
     */
    private function sendSuccessNotification(string $downloadUrl, int $count): void
    {
        $this->sendNotification(
            'Assessment Generation Complete',
            sprintf('Successfully generated %d assessments (sorted by Course → Year Level → Last Name).', $count),
            'success',
            $downloadUrl
        );
    }

    /**
     * Send notification to the user who initiated the job
     */
    private function sendNotification(string $title, string $body, string $type, ?string $downloadUrl = null): void
    {
        try {
            $user = User::query()->find($this->userId);
            if (! $user) {
                Log::warning('User not found for notification', [
                    'job_id' => $this->jobId,
                    'user_id' => $this->userId,
                ]);

                return;
            }

            $notification = Notification::make()
                ->title($title)
                ->body($body)
                ->persistent();

            // Set notification type
            match ($type) {
                'success' => $notification->success(),
                'warning' => $notification->warning(),
                'danger' => $notification->danger(),
                default => $notification->info(),
            };

            // Add download action if URL provided
            if (! in_array($downloadUrl, [null, '', '0'], true)) {
                $notification->actions([
                    Action::make('download')
                        ->label('Download PDF')
                        ->url($downloadUrl)
                        ->openUrlInNewTab()
                        ->icon('heroicon-o-arrow-down-tray'),
                ]);
            }

            $notification->sendToDatabase($user);

        } catch (Exception $exception) {
            Log::error('Failed to send notification', [
                'job_id' => $this->jobId,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
