<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\StudentEnrollment;
use App\Models\User;
use App\Notifications\PdfGenerationCompleted;
use App\Services\GeneralSettingsService;
use App\Services\PdfGenerationService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class GenerateAssessmentPdfJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    // Note: SerializesModels is intentionally NOT used here to avoid serialization issues
    // with the StudentEnrollment model. We store the ID and fetch fresh in handle().

    public int $timeout = 180; // 3 minutes timeout

    public int $tries = 2;

    private string $jobId;

    /**
     * The enrollment ID to process (using ID instead of model to avoid serialization issues).
     */
    private int $enrollmentId;

    /**
     * Create a new job instance.
     *
     * @param  StudentEnrollment|int  $enrollment  The enrollment model or ID
     * @param  string|null  $jobId  Optional job ID for tracking
     * @param  bool  $createNewFile  Whether to create a new file instead of updating existing
     */
    public function __construct(
        StudentEnrollment|int $enrollment,
        ?string $jobId = null,
        private bool $createNewFile = false
    ) {
        // Store the ID instead of the model to avoid SerializesModels issues
        $this->enrollmentId = $enrollment instanceof StudentEnrollment ? $enrollment->id : $enrollment;
        $this->jobId = $jobId ?? uniqid('pdf_', true);

        // Set queue name
        $this->onQueue('pdf-generation');

        Log::info('GenerateAssessmentPdfJob created', [
            'enrollment_id' => $this->enrollmentId,
            'job_id' => $this->jobId,
            'create_new_file' => $this->createNewFile,
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Initialize services here (not in constructor to avoid serialization)
        $pdfService = app(PdfGenerationService::class);

        try {
            // Fetch the enrollment fresh from the database to avoid serialization issues
            $studentEnrollment = StudentEnrollment::query()
                ->withoutGlobalScopes()
                ->findOrFail($this->enrollmentId);

            Log::info('Starting PDF generation job', [
                'job_id' => $this->jobId,
                'enrollment_id' => $studentEnrollment->id,
            ]);

            $this->updateProgress(10, 'Initializing PDF generation...');

            // Check if PDF already exists and is recent
            $existingResource = $studentEnrollment
                ->resources()
                ->where('type', 'assessment')
                ->where('created_at', '>', now()->subHours(1)) // Consider PDFs from last hour as fresh
                ->first();

            if (
                $existingResource &&
                file_exists($existingResource->file_path)
            ) {
                Log::info('Using existing recent PDF', [
                    'job_id' => $this->jobId,
                    'existing_path' => $existingResource->file_path,
                ]);
                $this->updateProgress(100, 'Using existing PDF');

                return;
            }

            $this->updateProgress(25, 'Preparing data...');

            // Generate PDF
            $pdfPath = $this->generatePdf($studentEnrollment, $pdfService);

            $this->updateProgress(90, 'Saving PDF record...');

            // Save resource record
            $this->saveResourceRecord($studentEnrollment, $pdfPath);

            $this->updateProgress(100, 'PDF generated successfully');

            // Send success notification to super_admin users
            $this->sendNotificationToSuperAdmins($studentEnrollment, false, 'PDF generated successfully');

            Log::info('PDF generation job completed successfully', [
                'job_id' => $this->jobId,
                'enrollment_id' => $studentEnrollment->id,
                'pdf_path' => $pdfPath,
            ]);
        } catch (Exception $exception) {
            $this->updateProgress(100, 'Failed: '.$exception->getMessage(), true);

            // Send failure notification to super_admin users
            $this->sendNotificationToSuperAdmins(null, true, 'PDF generation failed', $exception->getMessage());

            Log::error('PDF generation job failed', [
                'job_id' => $this->jobId,
                'enrollment_id' => $this->enrollmentId,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $throwable): void
    {
        Log::error('PDF generation job failed permanently', [
            'job_id' => $this->jobId,
            'enrollment_id' => $this->enrollmentId,
            'error' => $throwable->getMessage(),
        ]);

        $this->updateProgress(
            100,
            'Failed permanently: '.$throwable->getMessage(),
            true
        );

        // Send failure notification to super_admin users
        $this->sendNotificationToSuperAdmins(null, true, 'PDF generation failed permanently', $throwable->getMessage());
    }

    /**
     * Get the job ID for tracking
     */
    public function getJobId(): string
    {
        return $this->jobId;
    }

    /**
     * Generate the PDF using PdfGenerationService
     */
    private function generatePdf(StudentEnrollment $studentEnrollment, PdfGenerationService $pdfService): string
    {
        $generalSettingsService = new GeneralSettingsService;

        // Prepare data for the view
        $data = [
            'student' => $studentEnrollment,
            'subjects' => $studentEnrollment->SubjectsEnrolled,
            'school_year' => mb_convert_encoding(
                $generalSettingsService->getCurrentSchoolYearString() ?? '',
                'UTF-8',
                'auto'
            ),
            'semester' => mb_convert_encoding(
                $generalSettingsService->getAvailableSemesters()[$generalSettingsService->getCurrentSemester()] ?? '',
                'UTF-8',
                'auto'
            ),
            'tuition' => $studentEnrollment->studentTuition,
            'general_settings' => $generalSettingsService->getGlobalSettingsModel(),
        ];

        // Generate unique filename
        $randomChars = mb_substr(
            str_shuffle(
                'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
            ),
            0,
            10
        );

        // Add timestamp if creating new file to ensure uniqueness
        $timestamp = $this->createNewFile ? '-'.now()->format('YmdHis') : '';
        $assessmentFileName = sprintf('assessment-%s%s-%s.pdf', $studentEnrollment->id, $timestamp, $randomChars);

        // Use the configured filesystem disk
        $storageDisk = config('filesystems.default');
        $storageDirectory = 'assessments';

        // Ensure directory exists
        Storage::disk($storageDisk)->makeDirectory($storageDirectory);

        // Generate to temporary file first
        $temporaryFilePath = tempnam(sys_get_temp_dir(), 'pdf_').'.pdf';

        $this->updateProgress(50, 'Rendering HTML...');

        // Use PdfGenerationService to generate PDF from view
        $pdfService->generatePdfFromView('pdf.assesment-form', $data, $temporaryFilePath, [
            'format' => 'A4',
            'landscape' => true,
            'print_background' => true,
            'margin_top' => '10mm',
            'margin_bottom' => '10mm',
            'margin_left' => '10mm',
            'margin_right' => '10mm',
        ]);

        $this->updateProgress(70, 'Converting to PDF...');

        // Verify file was created
        if (! file_exists($temporaryFilePath) || filesize($temporaryFilePath) === 0) {
            throw new Exception('PDF was not generated or is empty');
        }

        // Upload to configured storage with public visibility
        $storagePath = $storageDirectory.'/'.$assessmentFileName;
        Storage::disk($storageDisk)->put($storagePath, file_get_contents($temporaryFilePath), ['visibility' => 'public']);

        // Clean up temporary file
        unlink($temporaryFilePath);

        Log::info('PDF generated and uploaded successfully', [
            'job_id' => $this->jobId,
            'disk' => $storageDisk,
            'path' => $storagePath,
            'filename' => $assessmentFileName,
            'size' => Storage::disk($storageDisk)->size($storagePath),
        ]);

        return $storagePath;
    }

    /**
     * Save the resource record in database
     */
    private function saveResourceRecord(StudentEnrollment $studentEnrollment, string $pdfPath): void
    {
        $generalSettingsService = new GeneralSettingsService;
        $fileName = basename($pdfPath);

        // File is already uploaded to the configured storage disk
        Log::info('PDF already uploaded to configured storage', [
            'filename' => $fileName,
            'disk' => config('filesystems.default'),
        ]);

        // Save resource record - create new or update existing based on createNewFile flag
        if ($this->createNewFile) {
            // Always create a new resource record
            $studentEnrollment->resources()->create([
                'resourceable_id' => $studentEnrollment->id,
                'resourceable_type' => $studentEnrollment::class,
                'type' => 'assessment',
                'file_path' => $pdfPath,
                'file_name' => $fileName,
                'mime_type' => 'application/pdf',
                'disk' => config('filesystems.default'),
                'file_size' => Storage::disk(config('filesystems.default'))->size($pdfPath),
                'metadata' => [
                    'school_year' => mb_convert_encoding(
                        $generalSettingsService->getCurrentSchoolYearString() ?? '',
                        'UTF-8',
                        'auto'
                    ),
                    'semester' => mb_convert_encoding(
                        $generalSettingsService->getAvailableSemesters()[$generalSettingsService->getCurrentSemester()] ?? '',
                        'UTF-8',
                        'auto'
                    ),
                    'generation_method' => 'browsershot_job',
                    'generated_at' => format_timestamp_now(),
                    'is_new_version' => true,
                ],
            ]);
        } else {
            // Update or create existing resource record (original behavior)
            $studentEnrollment->resources()->updateOrCreate(
                [
                    'resourceable_id' => $studentEnrollment->id,
                    'resourceable_type' => $studentEnrollment::class,
                    'type' => 'assessment',
                ],
                [
                    'file_path' => $pdfPath,
                    'file_name' => $fileName,
                    'mime_type' => 'application/pdf',
                    'disk' => config('filesystems.default'),
                    'file_size' => Storage::disk(config('filesystems.default'))->size($pdfPath),
                    'metadata' => [
                        'school_year' => mb_convert_encoding(
                            $generalSettingsService->getCurrentSchoolYearString() ?? '',
                            'UTF-8',
                            'auto'
                        ),
                        'semester' => mb_convert_encoding(
                            $generalSettingsService->getAvailableSemesters()[$generalSettingsService->getCurrentSemester()] ?? '',
                            'UTF-8',
                            'auto'
                        ),
                        'generation_method' => 'browsershot_job',
                        'generated_at' => format_timestamp_now(),
                    ],
                ]
            );
        }

        Log::info('Resource record saved', [
            'job_id' => $this->jobId,
            'enrollment_id' => $this->enrollmentId,
        ]);
    }

    /**
     * Update job progress
     */
    private function updateProgress(
        int $percentage,
        string $message,
        bool $failed = false
    ): void {
        $progressData = [
            'percentage' => $percentage,
            'message' => $message,
            'failed' => $failed,
            'updated_at' => format_timestamp_now(),
            'enrollment_id' => $this->enrollmentId,
            'type' => 'pdf_generation',
        ];

        // Store in Redis with 1 hour expiration
        cache()->put('pdf_job_progress:'.$this->jobId, $progressData, 3600);

        Log::info('PDF job progress updated', [
            'job_id' => $this->jobId,
            'progress' => $progressData,
        ]);
    }

    /**
     * Send notification to all super_admin users
     */
    private function sendNotificationToSuperAdmins(?StudentEnrollment $studentEnrollment, bool $failed, string $message, ?string $errorMessage = null): void
    {
        try {
            // Get all super_admin users
            $superAdmins = User::role('super_admin')->get();

            if ($superAdmins->isEmpty()) {
                Log::warning('No super_admin users found to notify about PDF generation completion', [
                    'job_id' => $this->jobId,
                    'enrollment_id' => $this->enrollmentId,
                ]);

                return;
            }

            // Ensure we have the enrollment model for notification
            $enrollment = $studentEnrollment ?? StudentEnrollment::query()
                ->withoutGlobalScopes()
                ->find($this->enrollmentId);

            if (! $enrollment) {
                Log::warning('Could not find enrollment to send notification', [
                    'job_id' => $this->jobId,
                    'enrollment_id' => $this->enrollmentId,
                ]);

                return;
            }

            // Send notification to all super_admin users
            LaravelNotification::send(
                $superAdmins,
                new PdfGenerationCompleted(
                    $enrollment,
                    $failed,
                    $message,
                    $errorMessage
                )
            );

            Log::info('Notification sent to super_admin users', [
                'job_id' => $this->jobId,
                'enrollment_id' => $this->enrollmentId,
                'failed' => $failed,
                'recipients_count' => $superAdmins->count(),
            ]);
        } catch (Exception $exception) {
            Log::error('Failed to send notification to super_admin users', [
                'job_id' => $this->jobId,
                'enrollment_id' => $this->enrollmentId,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }
}
