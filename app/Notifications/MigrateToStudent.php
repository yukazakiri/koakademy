<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\GeneralSetting;
use App\Services\PdfGenerationService;
use App\Settings\SiteSettings;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class MigrateToStudent extends Notification implements ShouldQueue
{
    use Queueable;

    private ?string $generatedPdfPath = null;

    /**
     * Create a new notification instance.
     */
    public function __construct(public $record) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $assessmentPath = null;
        $pdfGenerationError = null;

        try {
            // Attempt to generate PDF and get the local path
            if (
                $this->generatedPdfPath &&
                file_exists($this->generatedPdfPath)
            ) {
                $assessmentPath = $this->generatedPdfPath;
                Log::info('Using cached PDF path for email attachment.', [
                    'path' => $assessmentPath,
                ]);
            } else {
                $pdfContents = $this->generatePdf();
                if (isset($pdfContents['assessment']['path'])) {
                    $assessmentPath = $pdfContents['assessment']['path'];
                    $this->generatedPdfPath = $assessmentPath;
                    Log::info(
                        'PDF generated successfully for email attachment.',
                        [
                            'assessment_path' => $assessmentPath,
                            'file_exists' => file_exists($assessmentPath),
                            'file_size' => file_exists($assessmentPath)
                                ? filesize($assessmentPath)
                                : 'N/A',
                        ]
                    );
                } else {
                    Log::error(
                        'PDF generation method did not return a valid path.'
                    );
                    $pdfGenerationError =
                        'PDF generation process completed but did not return a valid file path.';
                }
            }
        } catch (Exception $exception) {
            $pdfGenerationError = $exception->getMessage();
            Log::error(
                'PDF Generation Failed During Email Preparation: '.
                    $pdfGenerationError
            );
            Log::error('Stack trace: '.$exception->getTraceAsString());
        }

        $generalSettings = GeneralSetting::query()->first();
        $siteSettings = app(SiteSettings::class)->getBrandingArray();

        $logoUrl = $siteSettings['logo'] ?? null;
        if ($logoUrl && ! str_starts_with($logoUrl, 'http')) {
            $logoUrl = url($logoUrl);
        }

        $mailMessage = (new MailMessage)
            ->subject(sprintf(
                'Enrollment Confirmation - %s',
                $siteSettings['organizationName'] ?? config('app.name')
            ))
            ->markdown('emails.enrollment.official_enrollment', [
                'student_name' => $this->record->first_name ?? 'Student',
                'school_year' => mb_convert_encoding(
                    (string) ($generalSettings?->getSchoolYearString() ?? ''),
                    'UTF-8',
                    'auto'
                ),
                'semester' => mb_convert_encoding(
                    (string) ($generalSettings?->getSemester() ?? ''),
                    'UTF-8',
                    'auto'
                ),
                'siteSettings' => $siteSettings,
                'logoUrl' => $logoUrl,
            ]);

        // Check if file exists in the correct storage location
        $fileExistsForAttachment = false;
        $attachmentPath = null;

        if ($assessmentPath) {
            // If file exists locally (absolute path), use it directly
            if (file_exists($assessmentPath)) {
                $attachmentPath = $assessmentPath;
                $fileExistsForAttachment = true;
                Log::info('Using local PDF file for attachment', [
                    'path' => $assessmentPath,
                ]);
            } else {
                // Try to find file in configured storage disk
                $storageDisk = config('filesystems.default');
                $storage = Storage::disk($storageDisk);
                $fileName = basename((string) $assessmentPath);
                $relativePath = 'assessments/'.$fileName;

                try {
                    // Check if file exists in storage disk
                    if ($storage->exists($relativePath)) {
                        // Create temporary file for attachment
                        $tempPath = tempnam(sys_get_temp_dir(), 'email_pdf_');
                        $fileContents = $storage->get($relativePath);
                        if ($fileContents) {
                            file_put_contents($tempPath, $fileContents);
                            $attachmentPath = $tempPath;
                            $fileExistsForAttachment = true;

                            Log::info('Successfully prepared PDF for email attachment from storage', [
                                'storage_path' => $relativePath,
                                'temp_path' => $attachmentPath,
                                'disk' => $storageDisk,
                            ]);
                        }
                    } elseif ($storage->exists($fileName)) {
                        // Fallback check in root
                        $tempPath = tempnam(sys_get_temp_dir(), 'email_pdf_');
                        $fileContents = $storage->get($fileName);
                        if ($fileContents) {
                            file_put_contents($tempPath, $fileContents);
                            $attachmentPath = $tempPath;
                            $fileExistsForAttachment = true;
                            Log::info('Successfully prepared PDF for email attachment from storage root', [
                                'storage_path' => $fileName,
                                'temp_path' => $attachmentPath,
                                'disk' => $storageDisk,
                            ]);
                        }
                    }
                } catch (Exception $e) {
                    Log::error('Error preparing PDF attachment from storage', [
                        'error' => $e->getMessage(),
                        'path' => $assessmentPath,
                    ]);
                }
            }
        }

        $pdfAttached = false;

        if ($fileExistsForAttachment && $attachmentPath) {
            $mailMessage->attach($attachmentPath, [
                'as' => sprintf('Assessment_Form_%s.pdf', $this->record->id),
                'mime' => 'application/pdf',
            ]);
            $pdfAttached = true;
            Log::info('Successfully attached PDF to email.', [
                'path' => $attachmentPath,
            ]);
        } else {
            Log::warning(
                'Assessment PDF file not found or generation failed for attachment.',
                [
                    'path_expected' => $assessmentPath,
                    'generation_error' => $pdfGenerationError,
                ]
            );

            if (! in_array($pdfGenerationError, [null, '', '0'], true)) {
                Log::error('PDF Generation Error Details: '.$pdfGenerationError);
            }
        }

        // Inject the attachment flag so the blade can show a fallback notice
        $mailMessage->with([
            'pdfAttached' => $pdfAttached,
        ]);

        return $mailMessage;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $assessmentPath = $this->generatedPdfPath;
        if (in_array($assessmentPath, [null, '', '0'], true)) {
            $assessmentPath = $this->record
                ->resources()
                ->where('type', 'assessment')
                ->first()?->file_path;
        }

        return [
            'title' => 'Enrollment Confirmation',
            'message' => 'Your enrollment has been successfully verified and processed.',
            'assessment_path' => $assessmentPath,
        ];
    }

    /**
     * Get the Filament representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        if (in_array($this->generatedPdfPath, [null, '', '0'], true)) {
            try {
                $pdfContents = $this->generatePdf();
                $this->generatedPdfPath =
                    $pdfContents['assessment']['path'] ?? null;
                Log::info('PDF generated for database notification.', [
                    'path' => $this->generatedPdfPath,
                ]);
            } catch (Exception $e) {
                Log::error(
                    'Failed to generate PDF for database notification: '.
                        $e->getMessage()
                );
                $this->generatedPdfPath = null;
            }
        }

        $resource = null;
        if ($this->generatedPdfPath && file_exists($this->generatedPdfPath)) {
            $fileName = basename($this->generatedPdfPath);
            $resource = $this->record
                ->resources()
                ->where('type', 'assessment')
                ->where('file_name', $fileName)
                ->first();
        }

        if (! $resource) {
            $resource = $this->record
                ->resources()
                ->where('type', 'assessment')
                ->latest()
                ->first();
            Log::warning(
                'Could not find exact resource match by filename, using latest.',
                [
                    'expected_filename' => basename(
                        $this->generatedPdfPath ?? 'N/A'
                    ),
                ]
            );
        }

        $notificationData = [
            'title' => 'Enrollment Confirmation',
            'message' => 'Your enrollment has been successfully verified and processed.',
        ];

        if ($resource) {
            Log::info('Preparing database notification with PDF resource.', [
                'assessment_path' => $resource->file_path,
                'file_exists_on_disk' => file_exists($resource->file_path),
                'resource_id' => $resource->id,
            ]);
            $notificationData['actions'] = [
                [
                    'label' => 'View Assessment',
                    'url' => route(
                        'filament.admin.resources.student-enrollments.view-resource',
                        [
                            'record' => $this->record->id,
                            'resourceId' => $resource->id,
                        ]
                    ),
                    'icon' => 'heroicon-o-document',
                ],
            ];
        } else {
            Log::error(
                'No assessment resource found or could be linked for database notification.',
                [
                    'enrollment_id' => $this->record->id,
                    'generated_path' => $this->generatedPdfPath,
                ]
            );
        }

        return $notificationData;
    }

    private function generatePdf(): array
    {
        $originalTimeLimitValue = ini_get('max_execution_time');
        $originalTimeLimit = is_numeric($originalTimeLimitValue) ? (int) $originalTimeLimitValue : 30; // Default to 30 if not numeric
        set_time_limit(180);

        try {
            Log::info('Attempting PDF generation with PdfGenerationService.');

            return $this->generatePdfWithService();
        } catch (Exception $exception) {
            Log::error(
                'PDF generation failed: '.$exception->getMessage()
            );
            Log::error('Stack trace: '.$exception->getTraceAsString());
            // Re-throw the exception so it can be caught by the toMail method
            throw $exception;
        } finally {
            set_time_limit($originalTimeLimit);
        }
    }

    private function generatePdfWithService(): array
    {
        $general_settings = GeneralSetting::query()->first();

        // Ensure all data is properly UTF-8 encoded for the Blade view
        $data = [
            'student' => $this->record,
            'subjects' => $this->record->SubjectsEnrolled,
            'school_year' => mb_convert_encoding(
                $general_settings->getSchoolYearString() ?? '',
                'UTF-8',
                'auto'
            ),
            'semester' => mb_convert_encoding(
                $general_settings->getSemester() ?? '',
                'UTF-8',
                'auto'
            ),
            'tuition' => $this->record->studentTuition,
            'siteSettings' => app(SiteSettings::class)->getBrandingArray(),
        ];

        $randomChars = mb_substr(
            str_shuffle(
                'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
            ),
            0,
            10
        );
        $assessmentFilename = sprintf('assmt-%s-%s.pdf', $this->record->id, $randomChars);

        // Use same storage as PDF jobs - use public disk and assessments directory
        $storageDisk = config('filesystems.default');
        $storageDirectory = 'assessments';
        $storage = Storage::disk($storageDisk);

        // Ensure directory exists
        $storage->makeDirectory($storageDirectory);

        // Generate to temporary file first
        $temporaryFilePath = tempnam(sys_get_temp_dir(), 'pdf_').'.pdf';

        Log::info('PDF file setup:', [
            'assessment_filename' => $assessmentFilename,
            'temp_path' => $temporaryFilePath,
        ]);

        // Increase memory limit for PDF generation
        $originalMemoryLimit = ini_get('memory_limit');
        if (mb_substr($originalMemoryLimit, 0, -1) < 1024) {
            ini_set('memory_limit', '1G');
            Log::info('Increased memory limit for PDF generation.', [
                'from' => $originalMemoryLimit,
                'to' => '1G',
            ]);
        }

        try {
            Log::info('Starting PDF generation using PdfGenerationService', [
                'view' => 'pdf.assesment-form',
                'original_memory_limit' => $originalMemoryLimit,
                'current_memory_limit' => ini_get('memory_limit'),
            ]);

            // Use PdfGenerationService
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

            Log::info('PDF saved locally via PdfGenerationService', [
                'path' => $temporaryFilePath,
                'size' => file_exists($temporaryFilePath) ? filesize($temporaryFilePath) : 0,
            ]);

            // Verify file existence and size
            if (
                ! file_exists($temporaryFilePath) ||
                filesize($temporaryFilePath) === 0
            ) {
                Log::error('Failed to save PDF or PDF is empty.', [
                    'path' => $temporaryFilePath,
                ]);
                throw new Exception(
                    'Failed to save PDF or generated PDF is empty.'
                );
            }

            // Upload to configured storage with public visibility
            $relativePath = $storageDirectory.'/'.$assessmentFilename;
            $storage->put($relativePath, file_get_contents($temporaryFilePath), ['visibility' => 'public']);

            // Clean up temporary file
            // Note: We might want to keep it for attachment if needed immediately,
            // but toMail logic seems to handle fetching from storage if local path is missing.
            // However, toMail expects 'path' in return. If we return temp path, it works.
            // But we should probably return the temp path and let the caller clean it up?
            // Or better, we return the temp path and rely on toMail to use it.
            // But wait, if we delete it here, toMail cannot use it as "local file".
            // toMail logic:
            // if ($assessmentPath && file_exists($assessmentPath)) { use it }
            // else { fetch from storage }

            // So if we delete it, toMail will fetch from storage. This is fine and consistent.
            unlink($temporaryFilePath);

            // But wait, toMail calls generatePdf() and uses the return value.
            // If we return the temp path but delete it, toMail will fail to find it locally.
            // Then it will try to fetch from storage using basename($assessmentPath).
            // If we return the relative path (storage path), toMail logic:
            // $fileName = basename($assessmentPath);
            // $relativePath = 'assessments/'.$fileName;
            // $storage->exists($relativePath) -> true.

            // So we should return the STORAGE path (relative path) or the full URL?
            // The original code returned $assessmentPath which was $storage->path($relativePath).
            // For local disk, that is absolute path. For S3, it is key.

            // Let's return the relative path, and ensure toMail handles it.
            // toMail logic:
            // if (file_exists($assessmentPath)) -> false for relative path
            // else -> tries storage->exists('assessments/'.basename($assessmentPath))

            // So if we return 'assessments/filename.pdf', basename is 'filename.pdf'.
            // 'assessments/filename.pdf' exists in storage.
            // So it should work.

            $assessmentPath = $relativePath;

            // Save resource record
            $this->record->resources()->updateOrCreate(
                [
                    'resourceable_id' => $this->record->id,
                    'resourceable_type' => $this->record::class,
                    'type' => 'assessment',
                ],
                [
                    'file_path' => $relativePath, // Store relative path for portability
                    'file_name' => $assessmentFilename,
                    'mime_type' => 'application/pdf',
                    'disk' => $storageDisk,
                    'file_size' => $storage->size($relativePath),
                    'metadata' => [
                        'school_year' => mb_convert_encoding(
                            (string) ($general_settings->getSchoolYear() ?? ''),
                            'UTF-8',
                            'auto'
                        ),
                        'semester' => mb_convert_encoding(
                            (string) ($general_settings->semester ?? ''),
                            'UTF-8',
                            'auto'
                        ),
                        'generation_method' => 'laravel_pdf_service',
                    ],
                ]
            );
            Log::info('Resource record created/updated in database.', [
                'enrollment_id' => $this->record->id,
            ]);

            return [
                'assessment' => [
                    'content' => null, // We don't need content in memory if we saved it
                    'path' => $assessmentPath,
                ],
            ];
        } catch (Exception $exception) {
            Log::error(
                'PDF Generation Error (PdfGenerationService): '.$exception->getMessage()
            );
            Log::error('Stack trace: '.$exception->getTraceAsString());
            throw new Exception(
                'Failed to generate PDF with PdfGenerationService: '.
                    $exception->getMessage(),
                0,
                $exception
            );
        } finally {
            // Restore original memory limit
            ini_set('memory_limit', $originalMemoryLimit);
        }
    }
}
