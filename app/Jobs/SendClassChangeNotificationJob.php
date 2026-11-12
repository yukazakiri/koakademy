<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Classes;
use App\Models\Resource;
use App\Models\StudentEnrollment;
use App\Services\AssessmentFormDataService;
use App\Services\GeneralSettingsService;
use App\Services\PdfGenerationService;
use App\Settings\SiteSettings;
use App\Support\PrivateExportStorage;
use App\Support\StreamedStorage;
use Exception;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class SendClassChangeNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;

    public int $tries = 3;

    private string $jobId;

    /**
     * @param  array<int, array{subject_code: string, subject_title: string, old_class_id: int|null, new_class_id: int|null, old_section: string, new_section: string}>  $classChanges
     */
    public function __construct(
        private StudentEnrollment $studentEnrollment,
        private array $classChanges,
        private string $changeReason,
        ?string $jobId = null
    ) {
        $this->jobId = $jobId ?? uniqid('class_change_', true);
        $this->onConnection((string) config('queue.assessment_notification_connection', config('queue.default')));
        $this->onQueue((string) config('queue.assessment_notification_queue', 'default'));

        Log::info('SendClassChangeNotificationJob created', [
            'job_id' => $this->jobId,
            'enrollment_id' => $this->studentEnrollment->id,
            'student_email' => $this->studentEnrollment->student?->email,
            'changes_count' => count($this->classChanges),
        ]);
    }

    public function handle(): void
    {
        try {
            Log::info('Starting class change notification job', [
                'job_id' => $this->jobId,
                'enrollment_id' => $this->studentEnrollment->id,
            ]);

            $student = $this->studentEnrollment->student;

            if (! $student?->email) {
                throw new Exception(
                    'Student email not found for enrollment ID: '.
                        $this->studentEnrollment->id
                );
            }

            // Ensure assessment PDF is available
            $assessment = $this->ensurePdfIsAvailable();
            $assessmentPath = $assessment['path'];

            // Resolve class details for the changes
            $classChanges = $this->resolveClassChangeDetails();

            $siteSettings = app(SiteSettings::class)->getBrandingArray();

            $logoUrl = $siteSettings['logo'] ?? null;
            if ($logoUrl && ! str_starts_with((string) $logoUrl, 'http')) {
                $logoUrl = url($logoUrl);
            }

            $generalSettingsService = new GeneralSettingsService;
            $schoolYear = mb_convert_encoding(
                $generalSettingsService->getCurrentSchoolYearString() ?? '',
                'UTF-8',
                'auto'
            );
            $semester = mb_convert_encoding(
                $generalSettingsService->getAvailableSemesters()[$generalSettingsService->getCurrentSemester()] ?? '',
                'UTF-8',
                'auto'
            );

            // Read PDF attachment data
            $pdfAttached = false;
            $attachmentData = $this->resolveAssessmentAttachment($assessmentPath, $assessment['disk']);

            // Send email with class change details
            Mail::to($student->email)
                ->send(new \App\Mail\ClassChangeNotification(
                    studentName: $student->full_name,
                    studentEmail: $student->email,
                    schoolYear: $schoolYear,
                    semester: $semester,
                    classChanges: $classChanges,
                    changeReason: $this->changeReason,
                    siteSettings: $siteSettings,
                    logoUrl: $logoUrl,
                    assessmentPath: $assessmentPath,
                    attachmentData: $attachmentData,
                ));

            if ($attachmentData !== null) {
                $pdfAttached = true;
            }

            Log::info('Class change notification email sent successfully.', [
                'job_id' => $this->jobId,
                'enrollment_id' => $this->studentEnrollment->id,
                'student_email' => $student->email,
                'pdf_attached' => $pdfAttached,
            ]);

            // Notify admins
            $this->notifyAdmins($student->full_name, $pdfAttached);

            Log::info('Class change notification job completed successfully', [
                'job_id' => $this->jobId,
                'enrollment_id' => $this->studentEnrollment->id,
            ]);
        } catch (Exception $exception) {
            Log::error('Class change notification job failed', [
                'job_id' => $this->jobId,
                'enrollment_id' => $this->studentEnrollment->id,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            $this->notifyAdminsOfFailure($exception->getMessage());

            throw $exception;
        }
    }

    public function failed(Throwable $throwable): void
    {
        Log::error('Class change notification job failed permanently', [
            'job_id' => $this->jobId,
            'enrollment_id' => $this->studentEnrollment->id,
            'error' => $throwable->getMessage(),
        ]);

        $this->notifyAdminsOfFailure($throwable->getMessage(), true);
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    /**
     * Resolve class/section names for the changes.
     *
     * @return array<int, array{subject_code: string, subject_title: string, old_section: string, new_section: string}>
     */
    private function resolveClassChangeDetails(): array
    {
        $resolved = [];

        foreach ($this->classChanges as $change) {
            $oldSection = $change['old_section'] ?? 'None';
            $newSection = $change['new_section'] ?? 'None';

            // Try to resolve section from class records if not already provided
            if (($change['old_section'] ?? '') === '' && $change['old_class_id']) {
                $oldClass = Classes::find($change['old_class_id']);
                $oldSection = $oldClass?->section ?? 'Unknown';
            }
            if (($change['new_section'] ?? '') === '' && $change['new_class_id']) {
                $newClass = Classes::find($change['new_class_id']);
                $newSection = $newClass?->section ?? 'Unknown';
            }

            $resolved[] = [
                'subject_code' => $change['subject_code'],
                'subject_title' => $change['subject_title'],
                'old_section' => $oldSection,
                'new_section' => $newSection,
            ];
        }

        return $resolved;
    }

    /**
     * Ensure assessment PDF is available, generate if needed.
     *
     * @return array{path: string, disk: string}
     */
    private function ensurePdfIsAvailable(): array
    {
        $existingResource = $this->studentEnrollment
            ->resources()
            ->where('type', 'assessment')
            ->latest('id')
            ->first();

        if ($existingResource) {
            $disk = $existingResource->disk ?: PrivateExportStorage::disk();
            $relativePath = $existingResource->file_path;

            try {
                if ($relativePath && Storage::disk($disk)->exists($relativePath)) {
                    Log::info('Existing assessment PDF found, skipping regeneration.', [
                        'job_id' => $this->jobId,
                        'resource_id' => $existingResource->id,
                    ]);

                    return ['path' => $relativePath, 'disk' => $disk];
                }
            } catch (Exception $exception) {
                Log::warning('Could not verify existing assessment resource.', [
                    'job_id' => $this->jobId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        Log::info('Assessment PDF not found, regenerating.', [
            'job_id' => $this->jobId,
            'enrollment_id' => $this->studentEnrollment->id,
        ]);

        try {
            return $this->generatePdfSynchronously();
        } catch (Exception $exception) {
            Log::error('Assessment PDF regeneration failed.', [
                'job_id' => $this->jobId,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * Generate PDF synchronously (reuses logic from SendAssessmentNotificationJob).
     *
     * @return array{path: string, disk: string}
     */
    private function generatePdfSynchronously(): array
    {
        $data = app(AssessmentFormDataService::class)->buildViewData($this->studentEnrollment);

        $randomChars = mb_substr(
            str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'),
            0,
            10
        );
        $assessmentFilename = sprintf('assmt-%s-%s.pdf', $this->studentEnrollment->id, $randomChars);

        $storageDisk = PrivateExportStorage::disk();
        $storageDirectory = 'assessments';
        $storage = Storage::disk($storageDisk);

        $storage->makeDirectory($storageDirectory);

        $temporaryFilePath = tempnam(sys_get_temp_dir(), 'pdf_').'.pdf';
        $relativePath = $storageDirectory.'/'.$assessmentFilename;

        try {
            $pdfService = app(PdfGenerationService::class);

            $pdfService->generatePdfFromView(
                'pdf.assesment-form',
                $data,
                $temporaryFilePath,
                [
                    'format' => 'A4',
                    'landscape' => true,
                    'print_background' => true,
                    'margin_top' => '10mm',
                    'margin_bottom' => '10mm',
                    'margin_left' => '10mm',
                    'margin_right' => '10mm',
                ]
            );

            if (! file_exists($temporaryFilePath) || filesize($temporaryFilePath) === 0) {
                throw new Exception('PDF was not generated or is empty');
            }

            StreamedStorage::putFileFromPath($storageDisk, $relativePath, $temporaryFilePath, ['visibility' => 'private']);
        } finally {
            if (file_exists($temporaryFilePath)) {
                unlink($temporaryFilePath);
            }
        }

        $storage->path($relativePath);

        try {
            $this->studentEnrollment
                ->resources()
                ->where('type', 'assessment')
                ->delete();

            Resource::query()->create([
                'resourceable_id' => $this->studentEnrollment->id,
                'resourceable_type' => $this->studentEnrollment::class,
                'type' => 'assessment',
                'file_path' => $relativePath,
                'file_name' => $assessmentFilename,
                'mime_type' => 'application/pdf',
                'disk' => $storageDisk,
                'file_size' => $storage->size($relativePath),
                'metadata' => [
                    'school_year' => $data['school_year'],
                    'semester' => $data['semester'],
                    'generation_method' => 'class_change_notification',
                ],
            ]);
        } catch (Exception $exception) {
            Log::warning('Could not save assessment resource record.', [
                'job_id' => $this->jobId,
                'error' => $exception->getMessage(),
            ]);
        }

        return ['path' => $relativePath, 'disk' => $storageDisk];
    }

    /**
     * Read the assessment PDF so it can be attached to the notification email.
     *
     * The disk the PDF is known to live on is tried first, because the export
     * may sit on the private export disk while the default disk still holds an
     * older copy of the same path.
     *
     * @return array{contents: string, name: string}|null
     */
    private function resolveAssessmentAttachment(?string $assessmentPath, ?string $assessmentDisk = null): ?array
    {
        if (in_array($assessmentPath, [null, '', '0'], true)) {
            return null;
        }

        // First try as local filesystem path
        if (file_exists($assessmentPath)) {
            return [
                'contents' => file_get_contents($assessmentPath),
                'name' => basename($assessmentPath),
            ];
        }

        // Try as storage-relative path, starting on the disk the PDF is known
        // to live on and falling back to the recorded and default disks.
        $resourceDisk = $this->studentEnrollment
            ->resources()
            ->where('type', 'assessment')
            ->latest('id')
            ->value('disk');

        $candidateDisks = PrivateExportStorage::candidateDisks(
            $assessmentDisk ?? (is_string($resourceDisk) ? $resourceDisk : null)
        );

        foreach ($candidateDisks as $disk) {
            try {
                if (! Storage::disk($disk)->exists($assessmentPath)) {
                    continue;
                }

                return [
                    'contents' => Storage::disk($disk)->get($assessmentPath),
                    'name' => basename($assessmentPath),
                ];
            } catch (Exception $exception) {
                Log::warning('Could not read assessment from storage.', [
                    'path' => $assessmentPath,
                    'disk' => $disk,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return null;
    }

    private function notifyAdmins(string $studentName, bool $pdfAttached): void
    {
        try {
            $admins = \App\Models\User::role('super_admin')->get();

            foreach ($admins as $admin) {
                Notification::make()
                    ->title('Class Change Notification Sent')
                    ->body(sprintf(
                        'Class change notification sent to %s for enrollment #%s. PDF %s.',
                        $studentName,
                        $this->studentEnrollment->id,
                        $pdfAttached ? 'attached' : 'not attached'
                    ))
                    ->success()
                    ->icon('heroicon-o-check-circle')
                    ->persistent()
                    ->sendToDatabase($admin);
            }
        } catch (Exception $exception) {
            Log::error('Failed to send admin notification for class change.', [
                'job_id' => $this->jobId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function notifyAdminsOfFailure(string $errorMessage, bool $permanent = false): void
    {
        try {
            $admins = \App\Models\User::role('super_admin')->get();

            foreach ($admins as $admin) {
                Notification::make()
                    ->title($permanent ? 'Class Change Notification Failed Permanently' : 'Class Change Notification Failed')
                    ->body(sprintf(
                        'Class change notification for enrollment #%s %s: %s',
                        $this->studentEnrollment->id,
                        $permanent ? 'failed permanently' : 'failed',
                        $errorMessage
                    ))
                    ->danger()
                    ->icon('heroicon-o-x-circle')
                    ->persistent()
                    ->sendToDatabase($admin);
            }
        } catch (Exception $exception) {
            Log::error('Failed to send admin failure notification for class change.', [
                'job_id' => $this->jobId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
