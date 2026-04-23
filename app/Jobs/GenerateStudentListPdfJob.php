<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Classes;
use App\Models\User;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class GenerateStudentListPdfJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $timeout = 300; // 5 minutes timeout

    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Classes $class,
        public ?int $userId = null
    ) {
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $pdfService = app(PdfGenerationService::class);

        try {
            Log::info('Starting student list PDF generation', [
                'class_id' => $this->class->id,
                'user_id' => $this->userId,
                'using_service' => 'PdfGenerationService', // Confirm which service is being used
            ]);

            // Efficiently load enrolled students with necessary relationships
            $enrolledStudents = $this->class->class_enrollments()
                ->with([
                    'student:id,student_id,first_name,last_name,middle_name,course_id,academic_year',
                    'student.course:id,code',
                ])
                ->where('status', true) // Only active enrollments
                ->get()
                ->sortBy([
                    ['student.last_name', 'asc'],
                    ['student.first_name', 'asc'],
                ]);

            // Prepare data for PDF
            $data = [
                'class' => $this->class,
                'students' => $enrolledStudents,
                'generated_at' => now()->format('F j, Y \a\t g:i A'),
                'total_students' => $enrolledStudents->count(),
            ];

            // Generate HTML content
            $html = view('exports.student-list-pdf', $data)->render();

            // Generate filename
            $filename = sprintf(
                'student_list_%s_%s_%s_%s.pdf',
                str_replace(' ', '_', $this->class->subject_code ?? 'Unknown'),
                str_replace(' ', '_', $this->class->section ?? 'Unknown'),
                str_replace(' ', '_', $this->class->semester ?? 'Unknown'),
                str_replace('-', '_', $this->class->school_year ?? 'Unknown')
            );

            // Use the configured filesystem disk
            $disk = config('filesystems.default');
            $directory = 'exports/student-lists';

            // Ensure directory exists
            Storage::disk($disk)->makeDirectory($directory);

            // Full path for the PDF
            $path = $directory.'/'.$filename;
            $tempPath = tempnam(sys_get_temp_dir(), 'pdf_').'.pdf';

            // Calculate scaling based on number of students
            $studentCount = $enrolledStudents->count();
            $scale = $this->calculateScale($studentCount);

            // Convert scale to percentage (0.8 = 80%)
            $scalePercentage = (int) ($scale * 100);

            // Generate PDF using PdfGenerationService
            $pdfOptions = [
                'headless' => true,
                'no-sandbox' => true,
                'disable-dev-shm-usage' => true,
                'disable-gpu' => true,
                'no-first-run' => true,
                'disable-background-timer-throttling' => true,
                'disable-backgrounding-occluded-windows' => true,
                'disable-renderer-backgrounding' => true,
                'print-to-pdf-no-header' => true,
                'run-all-compositor-stages-before-draw' => true,
                'disable-extensions' => true,
                'virtual-time-budget' => 10000,
            ];

            try {
                $pdfService->generatePdfFromHtml($html, $tempPath, $pdfOptions);

                // Upload to configured storage
                StreamedStorage::putFileFromPath($disk, $path, $tempPath);
            } finally {
                // Clean up temporary file
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
            }

            Log::info('Student list PDF generated and uploaded successfully', [
                'class_id' => $this->class->id,
                'filename' => $filename,
                'student_count' => $studentCount,
                'scale' => $scale,
                'disk' => $disk,
                'path' => $path,
            ]);

            // Send success notification to user
            if ($this->userId !== null && $this->userId !== 0) {
                $this->sendSuccessNotification($filename, $enrolledStudents->count());
            }

        } catch (Exception $exception) {
            Log::error('Failed to generate student list PDF', [
                'class_id' => $this->class->id,
                'user_id' => $this->userId,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            // Send error notification to user
            if ($this->userId !== null && $this->userId !== 0) {
                $this->sendErrorNotification($exception->getMessage());
            }

            throw $exception; // Re-throw to mark job as failed
        }
    }

    /**
     * Handle job failure
     */
    public function failed(Throwable $throwable): void
    {
        Log::error('Student list PDF job failed permanently', [
            'class_id' => $this->class->id,
            'user_id' => $this->userId,
            'error' => $throwable->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        if ($this->userId !== null && $this->userId !== 0) {
            $this->sendErrorNotification($throwable->getMessage());
        }
    }

    /**
     * Calculate appropriate scale based on number of students
     */
    private function calculateScale(int $studentCount): float
    {
        // Balanced scaling for readability and single-page fitting
        if ($studentCount <= 20) {
            return 1.0; // Full scale for small lists
        }
        if ($studentCount <= 30) {
            return 0.9; // Slightly smaller for medium lists
        }
        if ($studentCount <= 40) {
            return 0.8; // More compact for larger lists
        }
        if ($studentCount <= 50) {
            return 0.75; // Moderate compression for 40+ students
        }
        if ($studentCount <= 70) {
            return 0.7; // More compression for 50+ students
        }
        if ($studentCount <= 100) {
            return 0.65; // Strong compression for very large lists
        }

        return 0.6; // Maximum compression for 100+ students

    }

    /**
     * Send success notification to user
     */
    private function sendSuccessNotification(string $filename, int $studentCount): void
    {
        $downloadUrl = route('download.student-list', ['filename' => $filename]);

        Notification::make()
            ->title('Student List PDF Generated Successfully')
            ->body(sprintf('PDF generated with %d students. Click the download button below to get your file.', $studentCount))
            ->success()
            ->actions([
                Action::make('download')
                    ->label('Download PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->url($downloadUrl)
                    ->openUrlInNewTab(),
                Action::make('dismiss')
                    ->label('Dismiss')
                    ->color('gray')
                    ->close(),
            ])
            ->persistent()
            ->sendToDatabase(User::query()->find($this->userId))
            ->send();
    }

    /**
     * Send error notification to user
     */
    private function sendErrorNotification(string $error): void
    {
        Notification::make()
            ->title('PDF Generation Failed')
            ->body('Failed to generate student list PDF. Error: '.$error)
            ->danger()
            ->persistent()
            ->sendToDatabase(User::query()->find($this->userId));
    }
}
