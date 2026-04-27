<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\PdfGenerationService;
use App\Support\StreamedStorage;
use Exception;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class GenerateTimetablePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $retryAfter = 5;

    /**
     * The maximum number of seconds the job should run.
     */
    public int $timeout = 120;

    private PdfGenerationService $pdfService;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private array $scheduleData,
        private string $filename,
        private string $format = 'timetable',
        private ?int $userId = null
    ) {
        $this->pdfService = app(PdfGenerationService::class);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Determine the view based on format
            $viewName = match ($this->format) {
                'list' => 'pdf.schedule-list-export',
                'combined' => 'pdf.schedule-combined-export',
                default => 'pdf.timetable-export',
            };

            $disk = config('filesystems.default');

            $directory = 'schedules';

            // Ensure directory exists
            Storage::disk($disk)->makeDirectory($directory);

            // Generate PDF to temporary file first
            $tempPath = tempnam(sys_get_temp_dir(), 'pdf_').'.pdf';
            $storagePath = $directory.'/'.$this->filename;

            try {
                $this->pdfService->generatePdfFromView($viewName, $this->scheduleData, $tempPath);

                // Upload to configured storage
                StreamedStorage::putFileFromPath($disk, $storagePath, $tempPath);
            } finally {
                // Clean up temporary file
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
            }

            Log::info("Timetable PDF generated and uploaded successfully: {$this->filename}", [
                'disk' => $disk,
                'path' => $storagePath,
            ]);

            // Send Filament notification to user if available
            if ($this->userId) {
                $entityName = $this->scheduleData['entityName'] ?? 'Timetable';

                // Generate appropriate download URL
                try {
                    $downloadUrl = Storage::disk($disk)->temporaryUrl(
                        $storagePath,
                        now()->addHour()
                    );
                } catch (RuntimeException) {
                    // For disks that do not support temporary URLs, use the download route
                    $downloadUrl = route('download.timetable-pdf', ['filename' => $this->filename]);
                }

                // Get the user model
                $user = \App\Models\User::find($this->userId);
                if ($user) {
                    Notification::make()
                        ->title('Timetable PDF Generated Successfully')
                        ->body("The timetable PDF for {$entityName} is ready for download.")
                        ->success()
                        ->icon('heroicon-o-document-arrow-down')
                        // ->duration(10000)
                        ->actions([
                            \Filament\Actions\Action::make('download')
                                ->label('Download PDF')
                                ->url($downloadUrl)
                                ->openUrlInNewTab(),
                        ])
                        ->send()
                        ->broadcast($user)
                        ->sendToDatabase($user);
                }
            }

        } catch (Exception $e) {
            Log::error("Failed to generate timetable PDF {$this->filename}: ".$e->getMessage());
            Log::error('Stack trace: '.$e->getTraceAsString());

            // Re-throw the exception to mark the job as failed
            throw $e;
        }
    }

    /**
     * Get the filename that was generated.
     */
    public function getFilename(): string
    {
        return $this->filename;
    }
}
