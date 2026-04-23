<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Resource;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Notifications\MigrateToStudent;
use App\Services\GeneralSettingsService;
use App\Services\PdfGenerationService;
use App\Support\StreamedStorage;
use Exception;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class SendAssessmentNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300; // 5 minutes timeout

    public int $tries = 3;

    private string $jobId;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private StudentEnrollment $studentEnrollment,
        ?string $jobId = null
    ) {
        $this->jobId = $jobId ?? uniqid('assessment_', true);

        // Set queue name
        $this->onQueue('assessments');

        Log::info('SendAssessmentNotificationJob created', [
            'enrollment_id' => $this->studentEnrollment->id,
            'job_id' => $this->jobId,
            'student_email' => $this->studentEnrollment->student?->email,
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting assessment notification job', [
                'job_id' => $this->jobId,
                'enrollment_id' => $this->studentEnrollment->id,
            ]);

            if (! $this->studentEnrollment->student?->email) {
                throw new Exception(
                    'Student email not found for enrollment ID: '.
                        $this->studentEnrollment->id
                );
            }

            // Ensure PDF is generated and available
            $this->ensurePdfIsAvailable();

            // Send the notification
            NotificationFacade::route(
                'mail',
                $this->studentEnrollment->student->email
            )->notify(new MigrateToStudent($this->studentEnrollment));

            // Send database notification to super admins using Filament notifications
            try {
                $admins = User::role('super_admin')->get();
                Log::info('Preparing to send success notification', [
                    'job_id' => $this->jobId,
                    'enrollment_id' => $this->studentEnrollment->id,
                    'admin_count' => $admins->count(),
                ]);

                foreach ($admins as $admin) {
                    Notification::make()
                        ->title('Assessment Resent Successfully')
                        ->body(sprintf('Assessment notification successfully sent to %s %s (%s) for enrollment #%s', $this->studentEnrollment->student->first_name, $this->studentEnrollment->student->last_name, $this->studentEnrollment->student->email, $this->studentEnrollment->id))
                        ->success()
                        ->icon('heroicon-o-check-circle')
                        ->persistent()
                        ->sendToDatabase($admin);
                }

                Log::info(
                    'Success notification sent to database via Filament notifications',
                    [
                        'job_id' => $this->jobId,
                        'enrollment_id' => $this->studentEnrollment->id,
                        'admin_count' => $admins->count(),
                    ]
                );
            } catch (Exception $notifException) {
                Log::error(
                    'Exception occurred while sending success notification',
                    [
                        'job_id' => $this->jobId,
                        'enrollment_id' => $this->studentEnrollment->id,
                        'error' => $notifException->getMessage(),
                        'trace' => $notifException->getTraceAsString(),
                    ]
                );
            }

            Log::info('Assessment notification job completed successfully', [
                'job_id' => $this->jobId,
                'enrollment_id' => $this->studentEnrollment->id,
            ]);
        } catch (Exception $exception) {
            Log::error('Assessment notification job failed', [
                'job_id' => $this->jobId,
                'enrollment_id' => $this->studentEnrollment->id,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            // Send database notification to super admins using Filament notifications
            try {
                $admins = User::role('super_admin')->get();
                Log::info('Preparing to send failure notification', [
                    'job_id' => $this->jobId,
                    'enrollment_id' => $this->studentEnrollment->id,
                    'admin_count' => $admins->count(),
                    'error_message' => $exception->getMessage(),
                ]);

                foreach ($admins as $admin) {
                    Notification::make()
                        ->title('Assessment Resend Failed')
                        ->body(sprintf('Assessment notification failed for enrollment #%s: %s', $this->studentEnrollment->id, $exception->getMessage()))
                        ->danger()
                        ->icon('heroicon-o-x-circle')
                        ->persistent()
                        ->sendToDatabase($admin);
                }

                Log::info('Failure notification sent to admins', [
                    'job_id' => $this->jobId,
                    'enrollment_id' => $this->studentEnrollment->id,
                ]);
            } catch (Exception $notifException) {
                Log::error(
                    'Exception occurred while sending failure notification',
                    [
                        'job_id' => $this->jobId,
                        'enrollment_id' => $this->studentEnrollment->id,
                        'error' => $notifException->getMessage(),
                        'trace' => $notifException->getTraceAsString(),
                    ]
                );
            }

            throw $exception;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $throwable): void
    {
        Log::error('Assessment notification job failed permanently', [
            'job_id' => $this->jobId,
            'enrollment_id' => $this->studentEnrollment->id,
            'error' => $throwable->getMessage(),
        ]);

        // Send database notification to super admins using Filament notifications
        try {
            $admins = User::role('super_admin')->get();
            Log::info('Preparing to send permanent failure notification', [
                'job_id' => $this->jobId,
                'enrollment_id' => $this->studentEnrollment->id,
                'admin_count' => $admins->count(),
                'exception_message' => $throwable->getMessage(),
            ]);

            foreach ($admins as $admin) {
                Notification::make()
                    ->title('Assessment Resend Failed Permanently')
                    ->body(sprintf('Assessment notification job for enrollment #%s failed permanently after all retries: %s', $this->studentEnrollment->id, $throwable->getMessage()))
                    ->danger()
                    ->icon('heroicon-o-exclamation-triangle')
                    ->persistent()
                    ->sendToDatabase($admin);
            }

            Log::info('Permanent failure notification sent to admins', [
                'job_id' => $this->jobId,
                'enrollment_id' => $this->studentEnrollment->id,
            ]);
        } catch (Exception $exception) {
            Log::error(
                'Exception occurred while sending permanent failure notification',
                [
                    'job_id' => $this->jobId,
                    'enrollment_id' => $this->studentEnrollment->id,
                    'error' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString(),
                ]
            );
        }
    }

    /**
     * Get the job ID for tracking
     */
    public function getJobId(): string
    {
        return $this->jobId;
    }

    /**
     * Ensure PDF is available, generate if needed
     */
    private function ensurePdfIsAvailable(): void
    {
        // Check if PDF already exists and is recent
        $existingResource = $this->studentEnrollment
            ->resources()
            ->where('type', 'assessment')
            ->where('created_at', '>', now()->subHours(1))
            ->first();

        if ($existingResource && file_exists($existingResource->file_path)) {
            Log::info('PDF already exists, using existing file', [
                'job_id' => $this->jobId,
                'existing_path' => $existingResource->file_path,
            ]);

            return;
        }

        Log::info('PDF not found or expired, generating new PDF', [
            'job_id' => $this->jobId,
            'enrollment_id' => $this->studentEnrollment->id,
        ]);

        // Generate PDF synchronously
        $this->generatePdfSynchronously();
    }

    /**
     * Generate PDF synchronously
     */
    private function generatePdfSynchronously(): void
    {
        $generalSettingsService = new GeneralSettingsService;

        // Load additional fees relationship if not already loaded
        if (! $this->studentEnrollment->relationLoaded('additionalFees')) {
            $this->studentEnrollment->load('additionalFees');
        }

        // Prepare data for the view
        $data = [
            'student' => $this->studentEnrollment,
            'subjects' => $this->studentEnrollment->SubjectsEnrolled,
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
            'tuition' => $this->studentEnrollment->studentTuition,
            'general_settings' => $generalSettingsService->getGlobalSettingsModel(),
            'siteSettings' => app(\App\Settings\SiteSettings::class)->getBrandingArray(),
        ];

        // Generate unique filename
        $randomChars = mb_substr(
            str_shuffle(
                'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
            ),
            0,
            10
        );
        $assessmentFilename = sprintf('assmt-%s-%s.pdf', $this->studentEnrollment->id, $randomChars);

        // Use same storage as PDF jobs - use public disk and assessments directory
        $storageDisk = config('filesystems.default');
        $storageDirectory = 'assessments';
        $storage = Storage::disk($storageDisk);

        // Ensure directory exists
        $storage->makeDirectory($storageDirectory);

        // Generate to temporary file first (matches GenerateAssessmentPdfJob pattern)
        $temporaryFilePath = tempnam(sys_get_temp_dir(), 'pdf_').'.pdf';
        $relativePath = $storageDirectory.'/'.$assessmentFilename;

        try {
            Log::info('Generating PDF synchronously using PdfGenerationService', [
                'job_id' => $this->jobId,
                'temp_path' => $temporaryFilePath,
                'target_filename' => $assessmentFilename,
            ]);

            // Use PdfGenerationService
            $pdfService = app(PdfGenerationService::class);

            $pdfService->generatePdfFromView(
                'pdf.assesment-form',
                $data,
                $temporaryFilePath,
                [],
                'assessment_form'
            );

            // Verify file was created
            if (! file_exists($temporaryFilePath) || filesize($temporaryFilePath) === 0) {
                throw new Exception('PDF was not generated or is empty');
            }

            // Upload to configured storage with public visibility
            StreamedStorage::putFileFromPath($storageDisk, $relativePath, $temporaryFilePath, ['visibility' => 'public']);
        } finally {
            if (file_exists($temporaryFilePath)) {
                unlink($temporaryFilePath);
            }
        }

        $assessmentPath = $storage->path($relativePath);

        // Save resource record to database
        try {
            // Delete any existing assessment resources for this enrollment to avoid conflicts
            $this->studentEnrollment
                ->resources()
                ->where('type', 'assessment')
                ->delete();

            $resource = Resource::query()->create([
                'resourceable_id' => $this->studentEnrollment->id,
                'resourceable_type' => $this->studentEnrollment::class,
                'type' => 'assessment',
                'file_path' => $relativePath, // Store relative path for portability
                'file_name' => $assessmentFilename,
                'mime_type' => 'application/pdf',
                'disk' => $storageDisk,
                'file_size' => $storage->size($relativePath),
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
                    'generation_method' => 'pdf_generation_service',
                    'generated_at' => format_timestamp_now(),
                ],
            ]);

            Log::info('Resource record created successfully', [
                'job_id' => $this->jobId,
                'resource_id' => $resource->id,
                'enrollment_id' => $this->studentEnrollment->id,
                'file_name' => $assessmentFilename,
                'resource_table_check' => Resource::query()->where('resourceable_id', $this->studentEnrollment->id)
                    ->where('type', 'assessment')
                    ->count(),
            ]);
        } catch (Exception $exception) {
            Log::error('Failed to save resource record to database', [
                'job_id' => $this->jobId,
                'enrollment_id' => $this->studentEnrollment->id,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'file_path' => $assessmentPath,
            ]);

            // Don't throw exception here, as the PDF was generated successfully
            // Just log the error and continue
        }

        Log::info('PDF generated and saved successfully', [
            'job_id' => $this->jobId,
            'path' => $assessmentPath,
            'size' => $storage->size($relativePath),
        ]);
    }
}
